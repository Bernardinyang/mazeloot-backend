<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\EarlyAccessUser;
use App\Models\WebhookEvent;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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
                'cache_driver' => config('cache.default'),
                'queue_driver' => config('queue.default'),
                'session_driver' => config('session.driver'),
                'log_channel' => config('logging.default'),
                'log_level' => config('logging.level', config('logging.channels.'.config('logging.default').'.level', 'debug')),
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
                'display_errors' => ini_get('display_errors'),
                'error_reporting' => $this->errorReportingString(),
                'xdebug' => extension_loaded('xdebug'),
                'extensions' => get_loaded_extensions(),
                'extensions_count' => count(get_loaded_extensions()),
                'required_extensions' => $this->requiredExtensions(),
            ],
            'system_binaries' => $this->systemBinaries(),
            'database' => [
                'driver' => config('database.default'),
                'connection' => null,
                'host' => null,
                'port' => null,
                'database_name' => null,
                'username' => null,
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
                'failed_jobs' => $this->failedJobsList(50),
                'recent_processed_jobs' => Cache::get('admin.recent_processed_jobs', []),
            ],
            'scheduler_last_run' => Cache::get('admin.scheduler_last_run'),
            'scheduler_stale_seconds' => $this->schedulerStaleSeconds(),
            'quick_links' => array_values(array_filter(config('admin.quick_links', []), fn ($l) => ! empty($l['label']) && ! empty($l['url']))),
            'env_hint' => config('app.env').' | Debug '.((bool) config('app.debug') ? 'on' : 'off'),
            'feature_flags' => config('early_access.allowed_features', []),
            'feature_flag_usage' => $this->featureFlagUsage(),
            'failed_logins' => $this->failedLoginsFromCache(),
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
            $config = config('database.connections.'.config('database.default'), []);
            $data['database']['host'] = $config['host'] ?? null;
            $data['database']['port'] = $config['port'] ?? null;
            $data['database']['username'] = $config['username'] ?? null;
        } catch (\Throwable $e) {
            $data['database']['error'] = $e->getMessage();
        }

        $data['database_metrics'] = $this->databaseMetrics();

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

        $data['server'] = array_merge([
            'server_software' => request()->server('SERVER_SOFTWARE', 'unknown'),
            'document_root' => request()->server('DOCUMENT_ROOT'),
            'os' => PHP_OS_FAMILY,
            'os_version' => $this->osVersion(),
            'architecture' => php_uname('m'),
            'user' => get_current_user(),
        ], $this->serverInfo());
        $data['php_security'] = $this->phpSecurityInfo();

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
        $defaultChannel = config('logging.default');
        $exists = file_exists($logPath);
        $sizeBytes = $exists ? @filesize($logPath) : null;
        $modifiedAt = $exists && ($m = @filemtime($logPath)) ? $m : null;
        $data['logging'] = [
            'channel' => $defaultChannel,
            'level' => config('logging.channels.'.$defaultChannel.'.level', config('logging.level', 'debug')),
            'path' => $logPath,
            'exists' => $exists,
            'size_bytes' => $sizeBytes,
            'size_formatted' => $sizeBytes !== null ? $this->formatBytes($sizeBytes) : null,
            'modified_at' => $modifiedAt ? date('c', $modifiedAt) : null,
            'modified_formatted' => $modifiedAt ? date('d/m/Y, H:i:s', $modifiedAt) : null,
        ];
        $data['logging'] = array_merge($data['logging'], $this->logFileStats($logPath));

        $data['telescope_enabled'] = config('telescope.enabled', false);
        $data['app_version'] = config('app.version');
        $data['service_connectivity'] = $this->serviceConnectivity();
        $data['ssl'] = $this->sslInfo();
        $data['worker_heartbeat_at'] = Cache::get('admin.worker_heartbeat');
        $data['node_npm'] = $this->nodeNpmVersions();
        $data['composer_dependencies'] = $this->composerDependencies();
        $data['env_vars'] = $this->envVarsMasked();
        $data['tooling'] = $this->toolingCounts();
        $data['system_health'] = [
            'scheduled_task_status' => $this->scheduledTaskStatus(),
            'queue_status' => $this->queueStatus(),
        ];
        $data['performance'] = $this->performanceData();
        $data['security'] = $this->securityData();
        $data['route_statistics'] = $this->routeStatistics();
        $data['mail_statistics'] = $this->mailStatistics();
        $data['git_info'] = $this->gitInfo();
        $data['dependency_audit'] = $this->dependencyAudit();
        $data['storage_breakdown'] = $this->storageBreakdown();
        $data['scheduled_commands'] = $this->scheduledCommands();
        $data['last_backup'] = $this->lastBackup();
        $data['active_sessions_count'] = $this->activeSessionsCount();
        $data['version_notes'] = $this->versionNotes();

        return ApiResponse::successOk($data);
    }

    public function connectivity(): JsonResponse
    {
        return ApiResponse::successOk([
            'service_connectivity' => $this->serviceConnectivity(),
        ]);
    }

    public function webhooks(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 50), 100);
        $provider = $request->get('provider');
        $status = $request->get('status');

        $query = WebhookEvent::query()->orderByDesc('created_at');
        if ($provider && in_array($provider, ['stripe', 'paystack', 'paypal', 'flutterwave'], true)) {
            $query->where('provider', $provider);
        }
        if ($status && in_array($status, ['processed', 'failed', 'received'], true)) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);
        $items = $paginator->getCollection()->map(fn (WebhookEvent $e) => [
            'id' => $e->id,
            'provider' => $e->provider,
            'event_type' => $e->event_type,
            'event_id' => $e->event_id,
            'status' => $e->status,
            'response_code' => $e->response_code,
            'error_message' => $e->error_message,
            'received_at' => $e->received_at?->toIso8601String(),
            'created_at' => $e->created_at->toIso8601String(),
        ]);

        return ApiResponse::successOk([
            'webhooks' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
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

    /**
     * @return array<int, array{uuid: string, connection: string, queue: string, display_name: string, exception: string, failed_at: string}>
     */
    private function failedJobsList(int $limit = 50): array
    {
        try {
            $rows = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit($limit)
                ->get();
            $out = [];
            foreach ($rows as $row) {
                $payload = json_decode($row->payload, true);
                $displayName = null;
                if (is_array($payload)) {
                    $displayName = $payload['displayName'] ?? $payload['data']['commandName'] ?? null;
                }
                $out[] = [
                    'uuid' => $row->uuid,
                    'connection' => $row->connection,
                    'queue' => $row->queue,
                    'display_name' => $displayName ?? 'Unknown',
                    'exception' => $row->exception ? substr($row->exception, 0, 500) : '',
                    'failed_at' => $row->failed_at,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function schedulerStaleSeconds(): ?int
    {
        $last = Cache::get('admin.scheduler_last_run');
        if (! $last) {
            return null;
        }
        try {
            return (int) now()->parse($last)->diffInSeconds(now(), false);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function featureFlagUsage(): array
    {
        return Cache::remember('admin.feature_flag_usage', 30, function () {
            $allowed = config('early_access.allowed_features', []);
            $usage = [];
            foreach ($allowed as $flag) {
                try {
                    $usage[$flag] = EarlyAccessUser::where('is_active', true)
                        ->whereJsonContains('feature_flags', $flag)
                        ->count();
                } catch (\Throwable $e) {
                    $usage[$flag] = 0;
                }
            }
            return $usage;
        });
    }

    private function failedLoginsFromCache(): array
    {
        return array_slice(Cache::get('admin.failed_logins', []), 0, 20);
    }

    private function requiredExtensions(): array
    {
        $required = [
            // Laravel core requirements
            'pdo' => ['required' => true, 'purpose' => 'Database abstraction'],
            'pdo_mysql' => ['required' => true, 'purpose' => 'MySQL database driver'],
            'pdo_sqlite' => ['required' => false, 'purpose' => 'SQLite database driver'],
            'pdo_pgsql' => ['required' => false, 'purpose' => 'PostgreSQL database driver'],
            'mbstring' => ['required' => true, 'purpose' => 'Multibyte string handling'],
            'openssl' => ['required' => true, 'purpose' => 'Encryption and SSL'],
            'sodium' => ['required' => true, 'purpose' => 'Modern encryption (Laravel)'],
            'tokenizer' => ['required' => true, 'purpose' => 'PHP tokenizer'],
            'json' => ['required' => true, 'purpose' => 'JSON encoding/decoding'],
            'ctype' => ['required' => true, 'purpose' => 'Character type checking'],
            'xml' => ['required' => true, 'purpose' => 'XML parsing'],
            'dom' => ['required' => true, 'purpose' => 'DOM manipulation'],
            'fileinfo' => ['required' => true, 'purpose' => 'File type detection'],
            'curl' => ['required' => true, 'purpose' => 'HTTP client (S3, Stripe, APIs)'],
            'bcmath' => ['required' => true, 'purpose' => 'Precision math (Stripe, payments)'],
            'filter' => ['required' => true, 'purpose' => 'Data filtering/validation'],
            'session' => ['required' => true, 'purpose' => 'Session handling'],
            'pcre' => ['required' => true, 'purpose' => 'Regular expressions'],
            // Cache, queue, broadcasting
            'redis' => ['required' => true, 'purpose' => 'Redis cache/queue/broadcasting'],
            // Image processing (Spatie Image)
            'imagick' => ['required' => false, 'purpose' => 'Image processing (Spatie Image)'],
            'gd' => ['required' => true, 'purpose' => 'Image processing (default driver)'],
            'exif' => ['required' => false, 'purpose' => 'Image metadata/orientation'],
            // Networking & integrations
            'sockets' => ['required' => false, 'purpose' => 'Socket connections (Redis, Pusher)'],
            // File handling
            'zip' => ['required' => false, 'purpose' => 'ZIP archive handling'],
            // Internationalization (currencies, locales)
            'intl' => ['required' => true, 'purpose' => 'Internationalization (currencies, locales)'],
            'iconv' => ['required' => false, 'purpose' => 'Character encoding conversion'],
            // Other
            'simplexml' => ['required' => false, 'purpose' => 'SimpleXML parsing'],
            'xmlwriter' => ['required' => false, 'purpose' => 'XML writing'],
        ];

        $loaded = array_map('strtolower', get_loaded_extensions());
        $result = [];

        foreach ($required as $ext => $meta) {
            $result[] = [
                'name' => $ext,
                'installed' => in_array(strtolower($ext), $loaded),
                'required' => $meta['required'],
                'purpose' => $meta['purpose'],
            ];
        }

        return $result;
    }

    private function systemBinaries(): array
    {
        $binaries = [
            // Core server
            'php' => ['required' => true, 'purpose' => 'PHP runtime'],
            'composer' => ['required' => true, 'purpose' => 'PHP dependency manager'],
            'nginx' => ['required' => false, 'purpose' => 'Web server'],
            'git' => ['required' => true, 'purpose' => 'Version control'],
            // Database
            'mysql' => ['required' => false, 'purpose' => 'MySQL client'],
            'redis-cli' => ['required' => false, 'purpose' => 'Redis CLI (cache/queue/broadcast)'],
            'redis-server' => ['required' => false, 'purpose' => 'Redis server'],
            // Media processing
            'ffmpeg' => ['required' => false, 'purpose' => 'Video/audio transcoding'],
            'ffprobe' => ['required' => false, 'purpose' => 'Media file analysis'],
            // Queue & process management
            'supervisord' => ['required' => false, 'purpose' => 'Process manager (queue workers)'],
            // Frontend build
            'node' => ['required' => false, 'purpose' => 'Node.js runtime'],
            'bun' => ['required' => false, 'purpose' => 'Bun JS runtime/package manager'],
            // Utilities
            'cron' => ['required' => false, 'purpose' => 'Task scheduling (Laravel scheduler)'],
            'curl' => ['required' => false, 'purpose' => 'CLI HTTP client'],
            'unzip' => ['required' => false, 'purpose' => 'Archive extraction (Composer)'],
        ];

        $versionFlags = [
            'mysql' => '-V',
            'redis-server' => '-v',
            'redis-cli' => '-v',
            'nginx' => '-v',
            'cron' => '-V',
            'supervisord' => '--version',
        ];

        $result = [];
        foreach ($binaries as $bin => $meta) {
            $path = null;
            $version = null;
            $installed = false;

            try {
                $which = trim((string) @shell_exec("which {$bin} 2>/dev/null"));
                if ($which) {
                    $installed = true;
                    $path = $which;
                    $flag = $versionFlags[$bin] ?? '--version';
                    $ver = trim((string) @shell_exec("{$bin} {$flag} 2>&1 | head -1"));
                    if ($ver) {
                        $version = $ver;
                    }
                }
            } catch (\Throwable) {
            }

            $result[] = [
                'name' => $bin,
                'installed' => $installed,
                'required' => $meta['required'],
                'purpose' => $meta['purpose'],
                'path' => $path,
                'version' => $version,
            ];
        }

        return $result;
    }

    private function serviceConnectivity(): array
    {
        $services = [];

        // Redis
        $services[] = $this->checkService('Redis', function () {
            $start = microtime(true);
            Redis::ping();
            return ['ms' => round((microtime(true) - $start) * 1000)];
        }, config('cache.default') === 'redis' || config('queue.default') === 'redis');

        // Database
        $services[] = $this->checkService('Database ('.config('database.default').')', function () {
            $start = microtime(true);
            DB::connection()->selectOne('SELECT 1');
            return [
                'ms' => round((microtime(true) - $start) * 1000),
                'name' => DB::connection()->getDatabaseName(),
            ];
        }, true);

        // S3 / Cloudflare R2
        $s3Disk = collect(['s3', 'r2', 'cloudflare'])->first(fn ($d) => array_key_exists($d, config('filesystems.disks', [])));
        if ($s3Disk) {
            $services[] = $this->checkService('Storage ('.$s3Disk.')', function () use ($s3Disk) {
                $start = microtime(true);
                Storage::disk($s3Disk)->directories('/');
                return ['ms' => round((microtime(true) - $start) * 1000), 'disk' => $s3Disk];
            }, false);
        }

        // Stripe
        $stripeSecret = config('payment.providers.stripe.secret_key') ?? config('services.stripe.secret');
        if ($stripeSecret) {
            $services[] = $this->checkService('Stripe', function () use ($stripeSecret) {
                $start = microtime(true);
                $response = Http::withToken($stripeSecret, 'Bearer')
                    ->timeout(5)
                    ->get('https://api.stripe.com/v1/balance');
                if ($response->failed()) {
                    throw new \RuntimeException('HTTP '.$response->status());
                }
                return ['ms' => round((microtime(true) - $start) * 1000)];
            }, false);
        }

        // PayPal
        $paypal = config('payment.providers.paypal');
        if (! empty($paypal['client_id']) && ! empty($paypal['client_secret'])) {
            $services[] = $this->checkService('PayPal', function () use ($paypal) {
                $start = microtime(true);
                $response = Http::withBasicAuth($paypal['client_id'], $paypal['client_secret'])
                    ->asForm()
                    ->timeout(5)
                    ->post($paypal['base_url'].'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
                if ($response->failed()) {
                    throw new \RuntimeException('HTTP '.$response->status());
                }
                return ['ms' => round((microtime(true) - $start) * 1000)];
            }, false);
        }

        // Paystack
        $paystackSecret = config('payment.providers.paystack.secret_key');
        if ($paystackSecret) {
            $services[] = $this->checkService('Paystack', function () use ($paystackSecret) {
                $start = microtime(true);
                $response = Http::withToken($paystackSecret)
                    ->timeout(5)
                    ->get('https://api.paystack.co/bank');
                if ($response->failed()) {
                    throw new \RuntimeException('HTTP '.$response->status());
                }
                return ['ms' => round((microtime(true) - $start) * 1000)];
            }, false);
        }

        // Flutterwave
        $fwSecret = config('payment.providers.flutterwave.secret_key');
        if ($fwSecret) {
            $services[] = $this->checkService('Flutterwave', function () use ($fwSecret) {
                $start = microtime(true);
                $response = Http::withToken($fwSecret)
                    ->timeout(5)
                    ->get('https://api.flutterwave.com/v3/banks?country=NG');
                if ($response->failed()) {
                    throw new \RuntimeException('HTTP '.$response->status());
                }
                return ['ms' => round((microtime(true) - $start) * 1000)];
            }, false);
        }

        // Pusher (HTTP API requires signed requests)
        $pusherKey = config('broadcasting.connections.pusher.key');
        $pusherSecret = config('broadcasting.connections.pusher.secret');
        $pusherAppId = config('broadcasting.connections.pusher.app_id');
        if ($pusherKey && $pusherSecret && $pusherAppId) {
            $services[] = $this->checkService('Pusher', function () use ($pusherKey, $pusherSecret, $pusherAppId) {
                $start = microtime(true);
                $cluster = config('broadcasting.connections.pusher.options.cluster', 'mt1');
                $path = '/apps/'.$pusherAppId.'/channels';
                $authTimestamp = (string) time();
                $authVersion = '1.0';
                $params = [
                    'auth_key' => $pusherKey,
                    'auth_timestamp' => $authTimestamp,
                    'auth_version' => $authVersion,
                ];
                ksort($params);
                $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC1738);
                $stringToSign = "GET\n{$path}\n{$queryString}";
                $authSignature = hash_hmac('sha256', $stringToSign, $pusherSecret, false);
                $url = "https://api-{$cluster}.pusher.com{$path}?{$queryString}&auth_signature=".$authSignature;
                $response = Http::timeout(5)->get($url);
                if ($response->failed()) {
                    throw new \RuntimeException('HTTP '.$response->status());
                }
                return ['ms' => round((microtime(true) - $start) * 1000)];
            }, false);
        }

        // Mail (resolve transport to verify config; does not open SMTP connection)
        $mailDriver = config('mail.default');
        if ($mailDriver && $mailDriver !== 'log' && $mailDriver !== 'array') {
            $services[] = $this->checkService('Mail ('.$mailDriver.')', function () use ($mailDriver) {
                $start = microtime(true);
                $mailer = app('mail.manager')->mailer();
                if (! method_exists($mailer, 'getSymfonyTransport')) {
                    throw new \RuntimeException('Mail transport not available');
                }
                $transport = $mailer->getSymfonyTransport();
                if ($transport === null) {
                    throw new \RuntimeException('Mail transport not configured');
                }
                return ['ms' => round((microtime(true) - $start) * 1000), 'driver' => $mailDriver];
            }, false);
        }

        // Cloudinary (config may be under services or upload.providers)
        $cloudName = config('services.cloudinary.cloud_name')
            ?? config('upload.providers.cloudinary.cloud_name')
            ?? env('CLOUDINARY_CLOUD_NAME');
        if ($cloudName) {
            $services[] = $this->checkService('Cloudinary', function () use ($cloudName) {
                $start = microtime(true);
                $response = Http::timeout(5)->get("https://res.cloudinary.com/{$cloudName}/image/upload/sample.jpg");
                if ($response->failed()) {
                    throw new \RuntimeException('HTTP '.$response->status());
                }
                return ['ms' => round((microtime(true) - $start) * 1000)];
            }, false);
        }

        return $services;
    }

    private function serverInfo(): array
    {
        $info = [
            'hostname' => function_exists('gethostname') ? @gethostname() : null,
            'server_addr' => request()->server('SERVER_ADDR'),
            'request_ip' => request()->ip(),
            'forwarded_for' => request()->server('HTTP_X_FORWARDED_FOR'),
            'uptime_seconds' => null,
            'uptime_human' => null,
            'memory_total_mb' => null,
            'memory_available_mb' => null,
            'memory_used_mb' => null,
            'cpu_cores' => null,
        ];

        if (is_readable('/proc/uptime')) {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime && preg_match('/^(\d+\.?\d*)/', $uptime, $m)) {
                $sec = (float) $m[1];
                $info['uptime_seconds'] = (int) $sec;
                $d = (int) ($sec / 86400);
                $h = (int) (($sec % 86400) / 3600);
                $min = (int) (($sec % 3600) / 60);
                $info['uptime_human'] = ($d ? $d.'d ' : '').$h.'h '.$min.'m';
            }
        } elseif (PHP_OS_FAMILY !== 'Windows') {
            $out = @shell_exec('uptime -p 2>/dev/null');
            if ($out) {
                $info['uptime_human'] = trim($out);
            }
        }

        if (is_readable('/proc/meminfo')) {
            $mem = @file_get_contents('/proc/meminfo');
            if ($mem) {
                $get = function ($key) use ($mem) {
                    return preg_match('/'.$key.':\s+(\d+)/', $mem, $m) ? (int) $m[1] : null;
                };
                $total = $get('MemTotal');
                $avail = $get('MemAvailable') ?? $get('MemFree');
                if ($total) {
                    $info['memory_total_mb'] = round($total / 1024, 1);
                    $info['memory_available_mb'] = $avail ? round($avail / 1024, 1) : null;
                    $info['memory_used_mb'] = $avail !== null ? round(($total - $avail) / 1024, 1) : null;
                }
            }
        }

        if (is_readable('/proc/cpuinfo')) {
            $cpu = @file_get_contents('/proc/cpuinfo');
            if ($cpu && preg_match_all('/^processor\s*:/m', $cpu, $m)) {
                $info['cpu_cores'] = count($m[0]);
            }
        } elseif (PHP_OS_FAMILY !== 'Windows') {
            $nproc = trim((string) @shell_exec('nproc 2>/dev/null'));
            if ($nproc !== '' && ctype_digit($nproc)) {
                $info['cpu_cores'] = (int) $nproc;
            }
        }

        return $info;
    }

    private function phpSecurityInfo(): array
    {
        $disable = ini_get('disable_functions');
        $list = $disable ? array_map('trim', explode(',', $disable)) : [];
        $list = array_filter($list);

        return [
            'disable_functions_count' => count($list),
            'disable_functions_sample' => array_slice($list, 0, 15),
            'allow_url_fopen' => (bool) ini_get('allow_url_fopen'),
            'allow_url_include' => (bool) ini_get('allow_url_include'),
            'expose_php' => (bool) ini_get('expose_php'),
            'safe_mode' => (bool) ini_get('safe_mode'),
        ];
    }

    private function sslInfo(): array
    {
        $appUrl = config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME);
        $appUrlHttps = strtolower((string) $scheme) === 'https';
        $currentSecure = request()->secure();

        $certValidFrom = null;
        $certValidUntil = null;
        if ($appUrlHttps) {
            $host = parse_url($appUrl, PHP_URL_HOST) ?: request()->getHost();
            $port = parse_url($appUrl, PHP_URL_PORT) ?: 443;
            $ctx = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $errno = 0;
            $errstr = '';
            $client = @stream_socket_client(
                "ssl://{$host}:{$port}",
                $errno,
                $errstr,
                5,
                STREAM_CLIENT_CONNECT,
                $ctx
            );
            if ($client) {
                $params = stream_context_get_params($client);
                fclose($client);
                if (! empty($params['options']['ssl']['peer_certificate'])) {
                    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                    if ($cert) {
                        $certValidFrom = isset($cert['validFrom_time_t']) ? date('c', $cert['validFrom_time_t']) : null;
                        $certValidUntil = isset($cert['validTo_time_t']) ? date('c', $cert['validTo_time_t']) : null;
                    }
                }
            }
        }

        return [
            'app_url_https' => $appUrlHttps,
            'current_request_secure' => $currentSecure,
            'cert_valid_from' => $certValidFrom,
            'cert_valid_until' => $certValidUntil,
        ];
    }

    private function errorReportingString(): string
    {
        $v = error_reporting();
        $constants = [E_ALL => 'E_ALL', E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE', E_NOTICE => 'E_NOTICE', E_DEPRECATED => 'E_DEPRECATED'];
        return array_key_exists($v, $constants) ? $constants[$v] : (string) $v;
    }

    private function osVersion(): ?string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $out = @shell_exec('sw_vers -productVersion 2>/dev/null');
            return $out ? trim($out) : null;
        }
        if (is_readable('/etc/os-release')) {
            $content = @file_get_contents('/etc/os-release');
            if ($content && preg_match('/^VERSION_ID="?([^"\n]+)"?/m', $content, $m)) {
                return trim($m[1], '"');
            }
        }
        return null;
    }

    private function nodeNpmVersions(): array
    {
        $out = ['node_version' => null, 'npm_version' => null];
        $node = trim((string) @shell_exec('node -v 2>/dev/null'));
        if ($node !== '') {
            $out['node_version'] = $node;
        }
        $npm = trim((string) @shell_exec('npm -v 2>/dev/null'));
        if ($npm !== '') {
            $out['npm_version'] = $npm;
        }
        return $out;
    }

    private function composerDependencies(): array
    {
        $lockPath = base_path('composer.lock');
        if (! is_readable($lockPath)) {
            return [];
        }
        $lock = json_decode((string) file_get_contents($lockPath), true);
        $packages = $lock['packages'] ?? [];
        $dev = $lock['packages-dev'] ?? [];
        $req = [];
        $jsonPath = base_path('composer.json');
        if (is_readable($jsonPath)) {
            $json = json_decode((string) file_get_contents($jsonPath), true);
            $req = array_merge($json['require'] ?? [], $json['require-dev'] ?? []);
        }
        $list = [];
        foreach (array_merge($packages, $dev) as $p) {
            $name = $p['name'] ?? '';
            $version = $p['version'] ?? '';
            $list[] = [
                'name' => $name,
                'installed_version' => $version,
                'required_version' => $req[$name] ?? '—',
            ];
        }
        usort($list, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return array_slice($list, 0, 100);
    }

    private function envVarsMasked(): array
    {
        $sensitive = ['password', 'secret', 'key', 'token', 'credentials', 'auth'];
        $vars = [];

        $envPath = base_path('.env');
        if (is_readable($envPath)) {
            $content = file_get_contents($envPath);
            $lines = preg_split('/\r\n|\r|\n/', $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $line = preg_replace('/^\s*export\s+/i', '', $line);
                $eq = strpos($line, '=');
                if ($eq !== false) {
                    $name = trim(substr($line, 0, $eq));
                    $value = trim(substr($line, $eq + 1));
                    if ($name !== '') {
                        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                            $value = substr($value, 1, -1);
                        }
                        $vars[$name] = $value;
                    }
                }
            }
        }

        foreach (array_merge(getenv() ?: [], $_ENV ?? []) as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            if ($v === false) {
                continue;
            }
            $vars[$k] = is_string($v) ? $v : json_encode($v);
        }

        foreach ($vars as $k => $v) {
            $lower = strtolower($k);
            $mask = false;
            foreach ($sensitive as $s) {
                if (str_contains($lower, $s)) {
                    $mask = true;
                    break;
                }
            }
            $vars[$k] = $mask ? '*********' : $v;
        }
        ksort($vars);
        return $vars;
    }

    private function toolingCounts(): array
    {
        $out = [
            'tinker_enabled' => class_exists(\Laravel\Tinker\TinkerServiceProvider::class),
            'horizon_enabled' => class_exists(\Laravel\Horizon\HorizonServiceProvider::class) && config('horizon.enabled', true),
            'scheduler_enabled' => true,
            'total_commands' => 0,
            'total_routes' => 0,
            'middleware_count' => 0,
            'views_count' => 0,
            'events_count' => 0,
            'listeners_count' => 0,
            'jobs_count' => 0,
            'notifications_count' => 0,
            'migrations_count' => 0,
            'seeders_count' => 0,
            'policies_count' => 0,
            'observers_count' => 0,
            'factories_count' => 0,
            'service_providers_count' => 0,
            'custom_commands_count' => 0,
        ];
        try {
            $out['total_commands'] = count(Artisan::all());
        } catch (\Throwable) {
        }
        try {
            $out['total_routes'] = count(Route::getRoutes());
        } catch (\Throwable) {
        }
        try {
            $out['middleware_count'] = count(app('router')->getMiddleware());
        } catch (\Throwable) {
        }
        $viewsDir = resource_path('views');
        if (is_dir($viewsDir)) {
            $count = 0;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir, \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && str_ends_with((string) $f->getFilename(), '.blade.php')) {
                    $count++;
                }
            }
            $out['views_count'] = $count;
        }
        $appDir = app_path();
        if (is_dir($appDir.'/Events')) {
            $out['events_count'] = count(glob($appDir.'/Events/*.php') ?: []);
        }
        if (is_dir($appDir.'/Listeners')) {
            $out['listeners_count'] = count(glob($appDir.'/Listeners/*.php') ?: []);
        }
        if (is_dir($appDir.'/Observers')) {
            $out['observers_count'] = count(glob($appDir.'/Observers/*.php') ?: []);
        }
        if (is_dir($appDir.'/Console/Commands')) {
            $out['custom_commands_count'] = count(glob($appDir.'/Console/Commands/*.php') ?: []);
        }
        if (is_dir($appDir.'/Jobs')) {
            $out['jobs_count'] = count(glob($appDir.'/Jobs/*.php') ?: []);
        }
        if (is_dir($appDir.'/Notifications')) {
            $out['notifications_count'] = count(glob($appDir.'/Notifications/*.php') ?: []);
        }
        $migrationsDir = database_path('migrations');
        if (is_dir($migrationsDir)) {
            $out['migrations_count'] = count(glob($migrationsDir.'/*.php') ?: []);
        }
        if (is_dir(database_path('seeders'))) {
            $out['seeders_count'] = count(glob(database_path('seeders').'/*.php') ?: []);
        }
        if (is_dir($appDir.'/Policies')) {
            $out['policies_count'] = count(glob($appDir.'/Policies/*.php') ?: []);
        }
        if (is_dir(database_path('factories'))) {
            $out['factories_count'] = count(glob(database_path('factories').'/*.php') ?: []);
        }
        $out['service_providers_count'] = count(config('app.providers', []));
        return $out;
    }

    private function scheduledTaskStatus(): string
    {
        $stale = $this->schedulerStaleSeconds();
        if ($stale === null) {
            return 'unknown';
        }
        return $stale <= 900 ? 'ok' : 'failed';
    }

    private function queueStatus(): string
    {
        if ($this->failedJobsCount() > 0) {
            return 'failed';
        }
        if (config('queue.default') === 'sync') {
            return 'ok';
        }
        $heartbeat = Cache::get('admin.worker_heartbeat');
        if ($heartbeat === null) {
            return 'unknown';
        }
        try {
            $sec = (int) now()->parse($heartbeat)->diffInSeconds(now(), false);
            return $sec <= 150 ? 'ok' : 'failed';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function databaseMetrics(): array
    {
        $out = [
            'version' => null,
            'tables_count' => null,
            'size_mb' => null,
            'size_bytes' => null,
            'current_connections' => null,
            'max_connections' => null,
            'usage_percent' => null,
            'largest_tables' => [],
        ];
        try {
            $driver = DB::connection()->getDriverName();
            $db = DB::connection()->getDatabaseName();

            if ($driver === 'mysql') {
                $v = DB::selectOne('SELECT VERSION() AS v');
                $out['version'] = $v->v ?? null;
                $out['tables_count'] = (int) DB::selectOne('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ?', [$db])->c;
                $row = DB::selectOne(
                    'SELECT COALESCE(SUM(data_length + index_length), 0) AS size FROM information_schema.tables WHERE table_schema = ?',
                    [$db]
                );
                $out['size_bytes'] = (int) ($row->size ?? 0);
                $out['size_mb'] = round($out['size_bytes'] / 1024 / 1024, 2);
                $curr = DB::selectOne('SELECT COUNT(*) AS c FROM information_schema.processlist');
                $out['current_connections'] = (int) ($curr->c ?? 0);
                $maxRow = DB::selectOne("SHOW VARIABLES LIKE 'max_connections'");
                $out['max_connections'] = $maxRow ? (int) ($maxRow->Value ?? $maxRow->value ?? 0) : null;
                if ($out['max_connections'] > 0 && $out['current_connections'] !== null) {
                    $out['usage_percent'] = round($out['current_connections'] / $out['max_connections'] * 100, 1);
                }
                $largest = DB::select(
                    "SELECT table_name AS name, ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE' ORDER BY (data_length + index_length) DESC LIMIT 15",
                    [$db]
                );
                $out['largest_tables'] = array_map(fn ($r) => ['name' => $r->name, 'size_mb' => (float) $r->size_mb], $largest);
            }
            if ($driver === 'pgsql') {
                $v = DB::selectOne('SELECT version() AS v');
                $out['version'] = $v->v ?? null;
                $out['tables_count'] = (int) DB::selectOne("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'")->count;
                $row = DB::selectOne("SELECT COALESCE(SUM(pg_total_relation_size(quote_ident(schemaname) || '.' || quote_ident(tablename))), 0)::bigint AS size FROM pg_tables WHERE schemaname = 'public'");
                $out['size_bytes'] = (int) ($row->size ?? 0);
                $out['size_mb'] = round($out['size_bytes'] / 1024 / 1024, 2);
                $curr = DB::selectOne('SELECT count(*) AS c FROM pg_stat_activity');
                $out['current_connections'] = (int) ($curr->c ?? 0);
                $max = DB::selectOne("SELECT setting::int AS m FROM pg_settings WHERE name = 'max_connections'");
                $out['max_connections'] = $max ? (int) $max->m : null;
                if ($out['max_connections'] > 0 && $out['current_connections'] !== null) {
                    $out['usage_percent'] = round($out['current_connections'] / $out['max_connections'] * 100, 1);
                }
                $largest = DB::select(
                    "SELECT relname AS name, ROUND((pg_total_relation_size(c.oid) / 1024.0 / 1024.0)::numeric, 2) AS size_mb FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relkind = 'r' ORDER BY pg_total_relation_size(c.oid) DESC LIMIT 15"
                );
                $out['largest_tables'] = array_map(fn ($r) => ['name' => $r->name, 'size_mb' => (float) $r->size_mb], $largest);
            }
            if ($driver === 'sqlite') {
                $v = DB::selectOne('SELECT sqlite_version() AS v');
                $out['version'] = 'SQLite ' . ($v->v ?? '');
                $path = config('database.connections.sqlite.database');
                if ($path && is_readable($path)) {
                    $out['size_bytes'] = filesize($path);
                    $out['size_mb'] = round($out['size_bytes'] / 1024 / 1024, 2);
                }
                $out['tables_count'] = (int) DB::selectOne("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->count;
            }
        } catch (\Throwable $e) {
            $out['error'] = 'Unable to read metrics: ' . $e->getMessage();
        }
        return $out;
    }

    private function performanceData(): array
    {
        $thresholdMs = (float) config('performance.slow_request_threshold_ms', 1000);
        $list = Cache::get('admin.slow_requests', []);
        if (! is_array($list)) {
            $list = [];
        }

        $aggregated = [];
        foreach ($list as $entry) {
            $route = $entry['route'] ?? $entry['uri'] ?? 'unknown';
            $dur = (float) ($entry['duration_ms'] ?? 0);
            if (! isset($aggregated[$route])) {
                $aggregated[$route] = ['count' => 0, 'total_ms' => 0, 'max_ms' => 0];
            }
            $aggregated[$route]['count']++;
            $aggregated[$route]['total_ms'] += $dur;
            $aggregated[$route]['max_ms'] = max($aggregated[$route]['max_ms'], $dur);
        }
        foreach ($aggregated as $route => $stats) {
            $aggregated[$route]['avg_ms'] = $stats['count'] > 0 ? round($stats['total_ms'] / $stats['count'], 2) : 0;
            unset($aggregated[$route]['total_ms']);
        }

        $recommendations = $this->performanceRecommendations($list, $aggregated, $thresholdMs);

        return [
            'threshold_ms' => $thresholdMs,
            'slow_requests' => array_slice($list, 0, 50),
            'aggregated_by_route' => $aggregated,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * @param  array<int, array{route: string, duration_ms: float, method?: string}>  $list
     * @param  array<string, array{count: int, avg_ms: float, max_ms: float}>  $aggregated
     * @return array<int, array{route: string, severity: string, message: string, fix: string}>
     */
    private function performanceRecommendations(array $list, array $aggregated, float $thresholdMs): array
    {
        $out = [];
        $seen = [];
        foreach ($aggregated as $route => $stats) {
            $avg = $stats['avg_ms'] ?? 0;
            $max = $stats['max_ms'] ?? 0;
            $count = $stats['count'] ?? 0;
            if ($avg < $thresholdMs && $max < $thresholdMs) {
                continue;
            }
            $key = $route;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $severity = $max >= 5000 ? 'critical' : ($max >= 3000 ? 'high' : 'medium');
            $message = "Route \"{$route}\" exceeded threshold: avg {$avg}ms, max {$max}ms ({$count} occurrence(s)).";
            $fix = $this->recommendationFix($route, $avg, $max, $count);
            $out[] = ['route' => $route, 'severity' => $severity, 'message' => $message, 'fix' => $fix];
        }
        usort($out, fn ($a, $b) => ['critical' => 0, 'high' => 1, 'medium' => 2][$b['severity']] <=> ['critical' => 0, 'high' => 1, 'medium' => 2][$a['severity']]);
        return $out;
    }

    private function recommendationFix(string $route, float $avgMs, float $maxMs, int $count): string
    {
        $tips = [];
        if (str_contains(strtolower($route), 'upload') || str_contains(strtolower($route), 'import')) {
            $tips[] = 'Process large uploads asynchronously with a queue job; return immediately and notify when done.';
        }
        if (str_contains(strtolower($route), 'export') || str_contains(strtolower($route), 'report')) {
            $tips[] = 'Generate exports/reports in a background job and provide a download link when ready.';
        }
        if ($avgMs > 2000) {
            $tips[] = 'Add eager loading (with()) to avoid N+1 queries; add database indexes for filtered/sorted columns.';
        }
        if ($maxMs > 5000) {
            $tips[] = 'Consider moving heavy work to a queue; use caching (Cache::remember) for expensive reads.';
        }
        if (preg_match('/\b(admin|dashboard|system)\b/i', $route)) {
            $tips[] = 'Admin endpoints: cache aggregated data where possible; paginate or limit result sets.';
        }
        if (empty($tips)) {
            $tips[] = 'Profile with Laravel Telescope or debugbar; check for N+1 queries, missing indexes, and uncached heavy queries.';
        }
        return implode(' ', array_slice($tips, 0, 2));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return number_format($bytes / 1024 / 1024, 2) . ' MB';
    }

    private function logFileStats(string $path): array
    {
        $out = [
            'errors_count' => 0,
            'warnings_count' => 0,
            'info_count' => 0,
            'total_analyzed' => 0,
        ];
        if (! is_readable($path)) {
            return $out;
        }
        $content = $this->tailLog($path, 1000);
        $lines = explode("\n", $content);
        $pattern = '/^\[([^\]]+)\]\s+\S+\.(ERROR|WARNING|INFO|ALERT|CRITICAL|EMERGENCY|DEBUG):/m';
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match($pattern, $line, $m)) {
                $out['total_analyzed']++;
                $level = $m[2];
                if (in_array($level, ['ERROR', 'CRITICAL', 'EMERGENCY'], true)) {
                    $out['errors_count']++;
                } elseif ($level === 'WARNING') {
                    $out['warnings_count']++;
                } elseif ($level === 'INFO') {
                    $out['info_count']++;
                }
            }
        }
        return $out;
    }

    private function tailLog(string $path, int $lines): string
    {
        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            return '';
        }
        $chunk = 8192;
        $size = filesize($path);
        $pos = max(0, $size - $chunk * 60);
        fseek($fp, $pos);
        $content = '';
        while ($pos < $size) {
            $content .= fread($fp, $chunk);
            $pos = ftell($fp);
        }
        fclose($fp);
        $all = explode("\n", $content);

        return implode("\n", array_slice($all, -$lines));
    }

    private function securityData(): array
    {
        $corsPaths = config('cors.paths', ['api/*']);
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowedPatterns = config('cors.allowed_origins_patterns', []);
        $origins = is_array($allowedOrigins) ? $allowedOrigins : [];
        $patterns = is_array($allowedPatterns) ? $allowedPatterns : [];
        $originsList = array_merge($origins, array_map(fn ($p) => $p . ' (pattern)', $patterns));

        return [
            'auth' => [
                'default_guard' => config('auth.defaults.guard', 'web'),
                'sanctum_enabled' => class_exists(\Laravel\Sanctum\SanctumServiceProvider::class),
                'sanctum_guard' => config('sanctum.guard', ['web']),
                'stateful_domains' => config('sanctum.stateful', []),
            ],
            'rate_limiting' => [
                'enabled' => true,
                'default_per_minute' => config('security.rate_limit_per_minute', 60),
            ],
            'cors' => [
                'paths' => $corsPaths,
                'supports_credentials' => (bool) config('cors.supports_credentials', true),
                'allowed_origins' => $originsList,
                'allowed_methods' => config('cors.allowed_methods', ['*']),
                'allowed_headers' => config('cors.allowed_headers', []),
                'max_age' => config('cors.max_age', 0),
            ],
            'csp_enabled' => config('security.csp_enabled', false),
            'hsts_enabled' => config('security.hsts_enabled', false),
        ];
    }

    private function routeStatistics(): array
    {
        $out = [
            'total_routes' => 0,
            'api_routes' => 0,
            'web_routes' => 0,
            'by_method' => [
                'GET' => 0,
                'POST' => 0,
                'PUT' => 0,
                'PATCH' => 0,
                'DELETE' => 0,
                'OPTIONS' => 0,
            ],
        ];
        try {
            $routes = Route::getRoutes();
            $out['total_routes'] = $routes->count();
            foreach ($routes as $route) {
                $uri = $route->uri();
                if (str_starts_with($uri, 'api') || str_starts_with($uri, 'api/')) {
                    $out['api_routes']++;
                } else {
                    $out['web_routes']++;
                }
                foreach ($route->methods() as $method) {
                    $method = strtoupper($method);
                    if ($method === 'HEAD') {
                        continue;
                    }
                    if (array_key_exists($method, $out['by_method'])) {
                        $out['by_method'][$method]++;
                    }
                }
            }
        } catch (\Throwable) {
            // leave zeros
        }
        return $out;
    }

    private function mailStatistics(): array
    {
        $driver = config('mail.default', 'smtp');
        $mailers = config('mail.mailers', []);
        $config = $mailers[$driver] ?? [];
        $host = $config['host'] ?? null;
        if ($host === null || $host === '') {
            $host = 'N/A';
        }
        return [
            'mail_driver' => $driver,
            'mail_host' => $host,
        ];
    }

    private function gitInfo(): array
    {
        $out = ['branch' => null, 'commit' => null, 'short_commit' => null, 'dirty' => false, 'last_commit_date' => null];
        $base = base_path();
        if (! is_dir($base.'/.git')) {
            return $out;
        }
        try {
            $branch = trim((string) @shell_exec("cd {$base} && git rev-parse --abbrev-ref HEAD 2>/dev/null"));
            if ($branch !== '') {
                $out['branch'] = $branch;
            }
            $commit = trim((string) @shell_exec("cd {$base} && git rev-parse HEAD 2>/dev/null"));
            if ($commit !== '') {
                $out['commit'] = $commit;
                $out['short_commit'] = substr($commit, 0, 7);
            }
            $status = trim((string) @shell_exec("cd {$base} && git status --porcelain 2>/dev/null"));
            $out['dirty'] = $status !== '';
            $date = trim((string) @shell_exec("cd {$base} && git log -1 --format=%cI 2>/dev/null"));
            if ($date !== '') {
                $out['last_commit_date'] = $date;
            }
        } catch (\Throwable) {
            // leave defaults
        }
        return $out;
    }

    private function dependencyAudit(): array
    {
        $out = ['composer_vulnerabilities' => null, 'npm_vulnerabilities' => null, 'error' => null];
        $base = base_path();
        try {
            $audit = trim((string) @shell_exec("cd {$base} && composer audit --format=json 2>/dev/null"));
            if ($audit !== '' && $audit !== 'null') {
                $json = json_decode($audit, true);
                if (is_array($json) && isset($json['vulnerabilities'])) {
                    $out['composer_vulnerabilities'] = count($json['vulnerabilities']);
                } elseif (is_array($json)) {
                    $out['composer_vulnerabilities'] = 0;
                }
            }
        } catch (\Throwable) {
            $out['error'] = 'Composer audit failed';
        }
        try {
            $npmLock = $base.'/package-lock.json';
            if (file_exists($npmLock)) {
                $npm = trim((string) @shell_exec("cd {$base} && npm audit --json 2>/dev/null"));
                if ($npm !== '') {
                    $json = json_decode($npm, true);
                    if (isset($json['metadata']) && isset($json['metadata']['vulnerabilities'])) {
                        $v = $json['metadata']['vulnerabilities'];
                        $out['npm_vulnerabilities'] = (int) ($v['info'] ?? 0) + (int) ($v['low'] ?? 0) + (int) ($v['moderate'] ?? 0) + (int) ($v['high'] ?? 0) + (int) ($v['critical'] ?? 0);
                    } elseif (isset($json['error'])) {
                        $out['npm_vulnerabilities'] = null;
                    }
                }
            }
        } catch (\Throwable) {
            // leave npm null
        }
        return $out;
    }

    private function storageBreakdown(): array
    {
        $paths = [
            'app' => storage_path('app'),
            'logs' => storage_path('logs'),
            'framework' => storage_path('framework'),
            'app_public' => storage_path('app/public'),
        ];
        $out = [];
        foreach ($paths as $key => $path) {
            $size = $this->dirSize($path);
            $out[$key] = [
                'path' => $path,
                'bytes' => $size,
                'formatted' => $size !== null ? $this->formatBytes($size) : 'N/A',
            ];
        }
        return $out;
    }

    private function dirSize(string $path): ?int
    {
        if (! is_dir($path)) {
            return null;
        }
        $size = 0;
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $size += $f->isFile() ? $f->getSize() : 0;
            }
        } catch (\Throwable) {
            return null;
        }
        return $size;
    }

    private function scheduledCommands(): array
    {
        $out = [];
        try {
            Artisan::call('schedule:list');
            $output = Artisan::output();
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_contains($line, 'Command') || str_contains($line, '---')) {
                    continue;
                }
                $nextDue = null;
                if (preg_match('/\s+Next Due:\s*(.+)$/', $line, $n)) {
                    $nextDue = trim($n[1]);
                    $line = preg_replace('/\s+Next Due:\s*.+$/', '', $line);
                }
                $line = preg_replace('/\s*\.+\s*$/', '', trim($line));
                if (preg_match('/^(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+)$/', $line, $m)) {
                    $out[] = [
                        'expression' => $m[1].' '.$m[2].' '.$m[3].' '.$m[4].' '.$m[5],
                        'command' => trim($m[6]),
                        'next_due' => $nextDue,
                    ];
                }
            }
        } catch (\Throwable) {
            // leave empty
        }
        return $out;
    }

    private function lastBackup(): ?array
    {
        $path = config('backup.last_run_path', storage_path('app/backup-last-run.txt'));
        if ($path === null || ! is_readable($path)) {
            $path = storage_path('app/backup-last-run.txt');
        }
        if (! is_readable($path)) {
            return null;
        }
        $content = trim((string) @file_get_contents($path));
        if ($content === '') {
            return null;
        }
        return [
            'last_run' => $content,
            'path' => $path,
        ];
    }

    private function activeSessionsCount(): ?int
    {
        $driver = config('session.driver');
        if ($driver !== 'database') {
            return null;
        }
        try {
            $table = config('session.table', 'sessions');

            return (int) DB::table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }

    private function versionNotes(): array
    {
        return [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
        ];
    }

    private function checkService(string $name, callable $check, bool $critical): array
    {
        try {
            $extra = $check();
            return array_merge([
                'name' => $name,
                'status' => 'ok',
                'critical' => $critical,
                'error' => null,
            ], $extra ?? []);
        } catch (\Throwable $e) {
            return [
                'name' => $name,
                'status' => 'error',
                'critical' => $critical,
                'error' => $e->getMessage(),
                'ms' => null,
            ];
        }
    }
}
