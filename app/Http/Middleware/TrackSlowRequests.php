<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackSlowRequests
{
    private const CACHE_KEY = 'admin.slow_requests';

    private const MAX_ENTRIES = 100;

    private const DEFAULT_TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $thresholdMs = (float) config('performance.slow_request_threshold_ms', 1000);
        if ($thresholdMs <= 0) {
            return;
        }

        $start = $request->server('REQUEST_TIME_FLOAT');
        if (! is_numeric($start)) {
            return;
        }

        $durationMs = (microtime(true) - (float) $start) * 1000;
        if ($durationMs < $thresholdMs) {
            return;
        }

        $route = $request->route();
        $routeName = $route ? $route->getName() : null;
        $uri = $route ? $route->uri() : $request->path();
        $label = $routeName ?: ($request->method() . ' /' . $uri);

        $entry = [
            'route' => $label,
            'method' => $request->method(),
            'uri' => $request->path(),
            'duration_ms' => round($durationMs, 2),
            'time' => now()->toIso8601String(),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        try {
            $list = Cache::get(self::CACHE_KEY, []);
            if (! is_array($list)) {
                $list = [];
            }
            array_unshift($list, $entry);
            $list = array_slice($list, 0, self::MAX_ENTRIES);
            Cache::put(self::CACHE_KEY, $list, self::DEFAULT_TTL_SECONDS);
        } catch (\Throwable) {
            // Fail silently
        }
    }
}
