<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function failed(): JsonResponse
    {
        $limit = (int) request('limit', 100);
        $limit = min(max($limit, 1), 200);
        try {
            $rows = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit($limit)
                ->get();
            $list = [];
            foreach ($rows as $row) {
                $payload = json_decode($row->payload, true);
                $displayName = is_array($payload)
                    ? ($payload['displayName'] ?? $payload['data']['commandName'] ?? 'Unknown')
                    : 'Unknown';
                $list[] = [
                    'uuid' => $row->uuid,
                    'connection' => $row->connection,
                    'queue' => $row->queue,
                    'display_name' => $displayName,
                    'exception' => $row->exception ? substr($row->exception, 0, 1000) : '',
                    'failed_at' => $row->failed_at,
                ];
            }
            return ApiResponse::successOk([
                'failed_jobs' => $list,
                'total' => (int) DB::table('failed_jobs')->count(),
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }
    }

    public function retryFailed(Request $request): JsonResponse
    {
        $uuid = $request->input('uuid');
        try {
            if ($uuid) {
                Artisan::call('queue:retry', ['id' => [$uuid]]);
            } else {
                Artisan::call('queue:retry', ['id' => ['all']]);
            }
            return ApiResponse::successOk(['message' => $uuid ? 'Job queued for retry' : 'All failed jobs queued for retry']);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }
    }

    public function forgetFailed(string $uuid): JsonResponse
    {
        try {
            Artisan::call('queue:forget', ['id' => $uuid]);
            return ApiResponse::successOk(['message' => 'Failed job removed']);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }
    }

    public function flushFailed(): JsonResponse
    {
        try {
            Artisan::call('queue:flush');
            return ApiResponse::successOk(['message' => 'All failed jobs removed']);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }
    }

    public function restart(): JsonResponse
    {
        try {
            Artisan::call('queue:restart');
            return ApiResponse::successOk(['message' => 'Queue workers will restart after processing current job']);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }
    }
}
