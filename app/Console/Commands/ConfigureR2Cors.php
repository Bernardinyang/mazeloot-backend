<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;

class ConfigureR2Cors extends Command
{
    protected $signature = 'r2:configure-cors {--origins=*}';

    protected $description = 'Configure CORS on R2 bucket';

    public function handle()
    {
        $origins = $this->option('origins') ?: ['*'];
        $bucket = config('filesystems.disks.r2.bucket');
        $endpoint = config('filesystems.disks.r2.endpoint');
        $key = config('filesystems.disks.r2.key');
        $secret = config('filesystems.disks.r2.secret');
        $region = config('filesystems.disks.r2.region', 'auto');

        if (! $bucket || ! $endpoint || ! $key || ! $secret) {
            $this->error('R2 configuration is incomplete. Check your .env file.');

            return 1;
        }

        $corsConfiguration = [
            'CORSRules' => [
                [
                    'AllowedOrigins' => $origins,
                    'AllowedMethods' => ['GET', 'HEAD'],
                    'AllowedHeaders' => ['*'],
                    'ExposeHeaders' => ['ETag'],
                    'MaxAgeSeconds' => 3600,
                ],
            ],
        ];

        try {
            $adapter = Storage::disk('r2')->getAdapter();
            $reflection = new ReflectionClass($adapter);

            // Try different property names that might contain the S3 client
            $clientPropertyNames = ['client', 's3Client', '_client'];
            $s3Client = null;

            foreach ($clientPropertyNames as $propName) {
                if ($reflection->hasProperty($propName)) {
                    $clientProperty = $reflection->getProperty($propName);
                    $clientProperty->setAccessible(true);
                    $s3Client = $clientProperty->getValue($adapter);
                    if ($s3Client && method_exists($s3Client, 'putBucketCors')) {
                        break;
                    }
                }
            }

            if ($s3Client && method_exists($s3Client, 'putBucketCors')) {
                $s3Client->putBucketCors([
                    'Bucket' => $bucket,
                    'CORSConfiguration' => $corsConfiguration,
                ]);

                $this->info('CORS configured successfully!');
                $this->line('Origins: '.implode(', ', $origins));

                return 0;
            }
        } catch (\Exception $e) {
            $this->line('Note: '.$e->getMessage());
        }

        $this->showManualInstructions($bucket, $corsConfiguration);

        return 1;
    }

    protected function showManualInstructions(?string $bucket = null, ?array $corsConfiguration = null): void
    {
        $bucket = $bucket ?: config('filesystems.disks.r2.bucket', 'your-bucket');
        $corsConfiguration = $corsConfiguration ?: [
            'CORSRules' => [
                [
                    'AllowedOrigins' => ['*'],
                    'AllowedMethods' => ['GET', 'HEAD'],
                    'AllowedHeaders' => ['*'],
                    'ExposeHeaders' => ['ETag'],
                    'MaxAgeSeconds' => 3600,
                ],
            ],
        ];

        $this->warn('Automatic CORS configuration not available.');
        $this->line('');
        $this->line('Configure CORS manually:');
        $this->line('1. Go to Cloudflare Dashboard > R2 > '.$bucket.' > Settings');
        $this->line('2. Find "CORS Policy" section');
        $this->line('3. Add the following JSON:');
        $this->line('');
        $this->line(json_encode($corsConfiguration, JSON_PRETTY_PRINT));
    }
}
