<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $start = microtime(true);
        $checks = [];
        $stats = [];

        $checks['app'] = ['status' => 'ok', 'message' => 'Application is running'];

        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $dbName = DB::connection()->getDatabaseName();
            DB::connection()->selectOne('SELECT 1');
            $ms = round((microtime(true) - $start) * 1000);
            $checks['database'] = [
                'status' => 'ok',
                'message' => "Connected to {$dbName}",
                'response_ms' => $ms,
            ];
            $stats['database_connection_ms'] = $ms;
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $cacheDriver = config('cache.default');
        try {
            $start = microtime(true);
            $key = 'health_ping_'.uniqid();
            Cache::put($key, true, 5);
            $hit = Cache::get($key);
            Cache::forget($key);
            $ms = round((microtime(true) - $start) * 1000);
            $checks['cache'] = [
                'status' => $hit ? 'ok' : 'error',
                'message' => $hit ? "Driver: {$cacheDriver}" : 'Read/write failed',
                'response_ms' => $ms,
            ];
            $stats['cache_response_ms'] = $ms;
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $queueDriver = config('queue.default');
        $queueSize = null;
        if ($queueDriver === 'sync') {
            $checks['queue'] = ['status' => 'ok', 'message' => 'Driver: sync'];
        } else {
            try {
                $queueSize = Queue::connection()->size('default');
                $checks['queue'] = ['status' => 'ok', 'message' => "Driver: {$queueDriver}"];
            } catch (\Throwable $e) {
                $checks['queue'] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }
        $stats['queue_size'] = $queueSize;
        try {
            $stats['failed_jobs_count'] = (int) DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            $stats['failed_jobs_count'] = null;
        }

        try {
            $path = 'health_check_'.uniqid().'.tmp';
            Storage::disk('local')->put($path, 'ok');
            $read = Storage::disk('local')->get($path);
            Storage::disk('local')->delete($path);
            $checks['storage'] = [
                'status' => ($read === 'ok') ? 'ok' : 'error',
                'message' => ($read === 'ok') ? 'Default disk writable' : 'Write/read failed',
            ];
        } catch (\Throwable $e) {
            $checks['storage'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $stats['memory_usage_mb'] = round(memory_get_usage(true) / 1024 / 1024, 2);
        $stats['memory_peak_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $stats['php_memory_limit'] = ini_get('memory_limit');
        $stats['php_max_execution_time'] = ini_get('max_execution_time');
        $keyExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl', 'fileinfo', 'tokenizer', 'ctype', 'bcmath'];
        $stats['php_extensions_loaded'] = array_values(array_intersect($keyExtensions, array_map('strtolower', get_loaded_extensions())));
        $stats['php_extensions_count'] = count(get_loaded_extensions());

        $stats['health_check_ms'] = (int) round((microtime(true) - $start) * 1000);
        $allOk = collect($checks)->every(fn ($c) => ($c['status'] ?? '') === 'ok');
        $timestamp = now()->toIso8601String();

        $payload = [
            'status' => $allOk ? 'healthy' : 'degraded',
            'checks' => $checks,
            'statistics' => $stats,
            'timestamp' => $timestamp,
            'version' => config('app.version'),
        ];
        if (! $allOk) {
            $payload['last_failed_at'] = $timestamp;
            $webhookUrl = config('admin.health_webhook_url');
            if ($webhookUrl) {
                try {
                    Http::timeout(5)->post($webhookUrl, array_merge($payload, ['event' => 'health_degraded']));
                } catch (\Throwable $e) {
                    // do not fail response
                }
            }
        }

        return ApiResponse::successOk($payload);
    }
}
