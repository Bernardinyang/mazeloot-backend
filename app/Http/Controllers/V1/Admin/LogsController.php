<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class LogsController extends Controller
{
    /** @var int Max lines to read from end of log file */
    private const TAIL_LINES = 2000;

    /** @var int Max entries per level to return */
    private const MAX_PER_LEVEL = 20;

    public function recent(): JsonResponse
    {
        $path = storage_path('logs/laravel.log');
        $errors = [];
        $warnings = [];
        $alerts = [];

        if (! File::exists($path) || ! File::isReadable($path)) {
            return ApiResponse::successOk([
                'errors' => [],
                'warnings' => [],
                'alerts' => [],
                'message' => 'Log file not found or not readable',
            ]);
        }

        $content = $this->tail($path, self::TAIL_LINES);
        $lines = array_reverse(explode("\n", $content));
        // Match [date] env.LEVEL: message (Laravel default format)
        $pattern = '/^\[([^\]]+)\]\s+\S+\.(ERROR|WARNING|ALERT|CRITICAL|EMERGENCY):\s*(.*)$/s';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match($pattern, $line, $m)) {
                $entry = ['date' => $m[1], 'level' => $m[2], 'message' => strlen($m[3]) > 500 ? substr($m[3], 0, 500).'…' : $m[3]];
                if (in_array($m[2], ['ERROR', 'CRITICAL', 'EMERGENCY'], true)) {
                    if (count($errors) < self::MAX_PER_LEVEL) {
                        $errors[] = $entry;
                    }
                } elseif ($m[2] === 'ALERT') {
                    if (count($alerts) < self::MAX_PER_LEVEL) {
                        $alerts[] = $entry;
                    }
                } elseif ($m[2] === 'WARNING') {
                    if (count($warnings) < self::MAX_PER_LEVEL) {
                        $warnings[] = $entry;
                    }
                }
            }
        }

        return ApiResponse::successOk([
            'errors' => array_reverse($errors),
            'warnings' => array_reverse($warnings),
            'alerts' => array_reverse($alerts),
        ]);
    }

    private function tail(string $path, int $lines): string
    {
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            return '';
        }
        $chunk = 8192;
        $size = filesize($path);
        $pos = max(0, $size - $chunk * 50);
        fseek($fp, $pos);
        $content = '';
        while ($pos < $size) {
            $content .= fread($fp, $chunk);
            $pos = ftell($fp);
        }
        fclose($fp);
        $all = explode("\n", $content);
        $last = array_slice($all, -$lines);

        return implode("\n", $last);
    }
}
