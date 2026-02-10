<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class SystemController extends Controller
{
    public function index(): JsonResponse
    {
        $data = [
            'application' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'laravel_version' => app()->version(),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'os' => PHP_OS,
                'sapi' => PHP_SAPI,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'timezone' => date_default_timezone_get(),
                'extensions' => get_loaded_extensions(),
                'extensions_count' => count(get_loaded_extensions()),
            ],
            'database' => [
                'driver' => config('database.default'),
                'connection' => null,
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'status' => null,
                'error' => null,
            ],
            'queue' => [
                'driver' => config('queue.default'),
                'connection' => config('queue.connections.'.config('queue.default').'.driver', config('queue.default')),
                'size' => $this->queueSize(),
                'failed_jobs_count' => $this->failedJobsCount(),
            ],
            'scheduler_last_run' => Cache::get('admin.scheduler_last_run'),
            'quick_links' => array_values(array_filter(config('admin.quick_links', []), fn ($l) => ! empty($l['label']) && ! empty($l['url']))),
            'env_hint' => config('app.env').' | Debug '.((bool) config('app.debug') ? 'on' : 'off'),
            'feature_flags' => config('early_access.allowed_features', []),
            'session' => [
                'driver' => config('session.driver'),
                'lifetime' => config('session.lifetime'),
            ],
            'filesystem' => [
                'default' => config('filesystems.default'),
                'disks' => array_keys(config('filesystems.disks', [])),
            ],
        ];

        try {
            $conn = DB::connection();
            $data['database']['connection'] = $conn->getDriverName();
            $data['database']['database_name'] = $conn->getDatabaseName();
        } catch (\Throwable $e) {
            $data['database']['error'] = $e->getMessage();
        }

        try {
            Cache::get('system_ping');
            Cache::put('system_ping', true, 10);
            $data['cache']['status'] = 'ok';
        } catch (\Throwable $e) {
            $data['cache']['status'] = 'error';
            $data['cache']['error'] = $e->getMessage();
        }

        if (function_exists('opcache_get_status') && opcache_get_status(false) !== false) {
            $opcache = opcache_get_status(false);
            $data['opcache'] = [
                'enabled' => true,
                'memory_used_mb' => round(($opcache['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 2),
                'memory_free_mb' => round(($opcache['memory_usage']['free_memory'] ?? 0) / 1024 / 1024, 2),
                'hit_rate' => isset($opcache['opcache_statistics']['opcache_hit_rate'])
                    ? round($opcache['opcache_statistics']['opcache_hit_rate'], 2).'%'
                    : null,
                'num_cached_scripts' => $opcache['opcache_statistics']['num_cached_scripts'] ?? null,
            ];
        } else {
            $data['opcache'] = ['enabled' => false];
        }

        $data['server'] = [
            'server_software' => request()->server('SERVER_SOFTWARE', 'unknown'),
            'document_root' => request()->server('DOCUMENT_ROOT'),
        ];

        $diskPath = storage_path();
        if (is_dir($diskPath)) {
            $total = @disk_total_space($diskPath);
            $free = @disk_free_space($diskPath);
            $data['disk'] = [
                'path' => $diskPath,
                'total_bytes' => $total ?: null,
                'free_bytes' => $free ?: null,
                'total_gb' => $total ? round($total / 1024 / 1024 / 1024, 2) : null,
                'free_gb' => $free ? round($free / 1024 / 1024 / 1024, 2) : null,
                'used_percent' => ($total && $free && $total > 0) ? round((($total - $free) / $total) * 100, 1) : null,
            ];
        } else {
            $data['disk'] = ['path' => $diskPath, 'error' => 'Path not found'];
        }

        if (function_exists('sys_getloadavg')) {
            $load = @sys_getloadavg();
            $data['load'] = $load ? ['1min' => $load[0], '5min' => $load[1], '15min' => $load[2]] : null;
        } else {
            $data['load'] = null;
        }

        $data['config'] = [
            'config_cached' => file_exists(base_path('bootstrap/cache/config.php')),
            'routes_cached' => file_exists(base_path('bootstrap/cache/routes-v7.php')) || file_exists(base_path('bootstrap/cache/routes.php')),
        ];

        $data['env'] = [
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_url' => config('app.url'),
            'log_channel' => config('logging.default'),
            'cache_driver' => config('cache.default'),
            'queue_connection' => config('queue.default'),
            'session_driver' => config('session.driver'),
            'db_connection' => config('database.default'),
        ];

        $logPath = storage_path('logs/laravel.log');
        $data['logging'] = [
            'channel' => config('logging.default'),
            'path' => $logPath,
            'exists' => file_exists($logPath),
            'size_bytes' => file_exists($logPath) ? @filesize($logPath) : null,
            'modified_at' => file_exists($logPath) && ($m = @filemtime($logPath)) ? date('c', $m) : null,
        ];

        $data['telescope_enabled'] = config('telescope.enabled', false);
        $data['app_version'] = config('app.version');

        return ApiResponse::successOk($data);
    }

    private function queueSize(): ?int
    {
        if (config('queue.default') === 'sync') {
            return null;
        }
        try {
            return Queue::connection()->size('default');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function failedJobsCount(): ?int
    {
        try {
            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
