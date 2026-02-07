<?php

namespace App\Domains\Memora\Jobs;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Services\Notification\NotificationService;
use App\Support\MemoraFrontendUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GenerateZipDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 60;

    public $timeout = 600; // 10 minutes for large uploads to cloud storage

    public function __construct(
        public string $token,
        public string $collectionId,
        public array $setIds,
        public string $size,
        public ?string $email,
        public string $destination = 'device',
        public ?string $downloaderEmail = null
    ) {}

    /**
     * Sanitize folder name to be safe for ZIP files
     */
    private function sanitizeFolderName(string $name): string
    {
        // Remove or replace invalid characters for folder names
        $name = preg_replace('/[<>:"|?*]/', '_', $name);
        // Remove leading/trailing spaces and dots
        $name = trim($name, ' .');
        // Replace multiple spaces with single space
        $name = preg_replace('/\s+/', ' ', $name);

        // Limit length to avoid issues
        return mb_substr($name, 0, 255);
    }

    public function handle(): void
    {
        try {
            $collection = MemoraCollection::where('uuid', $this->collectionId)->firstOrFail();

            // Create downloads directory if it doesn't exist
            $downloadsDir = storage_path('app/downloads');
            if (! is_dir($downloadsDir)) {
                mkdir($downloadsDir, 0755, true);
            }

            // Check if service supports ZIP uploads
            $cloudService = null;
            $supportsZip = true;
            $tokenData = null;
            if ($this->destination !== 'device') {
                $cloudService = \App\Services\CloudStorage\CloudStorageFactory::make($this->destination);
                $supportsZip = $cloudService->supportsZipUpload();

                // Get OAuth token early if needed for non-ZIP uploads
                if (! $supportsZip) {
                    $tokenKey = "cloud_token_{$this->destination}_{$this->token}";
                    $tokenData = \Illuminate\Support\Facades\Cache::get($tokenKey);

                    if (! $tokenData || ! isset($tokenData['access_token'])) {
                        $collectionTokenKey = "cloud_token_{$this->destination}_{$this->collectionId}";
                        $tokenData = \Illuminate\Support\Facades\Cache::get($collectionTokenKey);

                        if ($tokenData && isset($tokenData['access_token'])) {
                            \Illuminate\Support\Facades\Cache::put($tokenKey, $tokenData, now()->addHours(24));
                        }
                    }
                }
            }

            // Collect all media files organized by set
            $mediaFiles = [];
            $mediaCount = 0;

            // Get all media from selected sets, organized by set
            foreach ($this->setIds as $setId) {
                $set = MemoraMediaSet::where('uuid', $setId)
                    ->where('collection_uuid', $this->collectionId)
                    ->first();

                if (! $set) {
                    continue;
                }

                $setName = $this->sanitizeFolderName($set->name);

                $mediaItems = MemoraMedia::whereHas('mediaSet', function ($query) use ($setId) {
                    $query->where('uuid', $setId)->where('collection_uuid', $this->collectionId);
                })->with('file')->get();

                foreach ($mediaItems as $media) {
                    $file = $media->file;
                    if (! $file) {
                        continue;
                    }

                    $filePath = $file->path;
                    $fileUrl = $file->url;

                    // Determine which file to use based on size
                    $downloadUrl = null;
                    if ($fileUrl && (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://'))) {
                        $downloadUrl = $fileUrl;
                    } elseif ($filePath) {
                        $disks = ['s3', 'r2', 'local'];
                        foreach ($disks as $disk) {
                            if (Storage::disk($disk)->exists($filePath)) {
                                $downloadUrl = Storage::disk($disk)->url($filePath);
                                break;
                            }
                        }
                    }

                    if ($downloadUrl) {
                        try {
                            // Download file content
                            $fileContent = file_get_contents($downloadUrl);
                            if ($fileContent) {
                                $filename = $file->filename ?? ($media->title ?? "photo-{$mediaCount}.jpg");

                                // Detect MIME type from content
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                $mimeType = finfo_buffer($finfo, $fileContent);
                                finfo_close($finfo);

                                // Fallback to extension-based MIME type
                                if (! $mimeType || $mimeType === 'application/octet-stream') {
                                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                    $mimeTypes = [
                                        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                                        'png' => 'image/png', 'gif' => 'image/gif',
                                        'webp' => 'image/webp', 'heic' => 'image/heic',
                                        'mp4' => 'video/mp4', 'mov' => 'video/quicktime',
                                        'avi' => 'video/x-msvideo', 'mkv' => 'video/x-matroska',
                                    ];
                                    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
                                }

                                // Store file data for cloud upload (if needed)
                                $mediaFiles[] = [
                                    'path' => $downloadUrl,
                                    'name' => $filename,
                                    'folder' => $setName,
                                    'content' => $fileContent,
                                    'mime_type' => $mimeType,
                                ];
                                $mediaCount++;
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to download file', [
                                'media_id' => $media->uuid,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            if ($mediaCount === 0) {
                throw new \Exception('No media files found to download');
            }

            // If service supports ZIP, create ZIP file
            $zipFileName = null;
            $zipPath = null;
            $fullZipPath = null;
            $fileSize = 0;

            if ($supportsZip) {
                $zipFileName = "{$collection->name}-photo-download-{$this->token}.zip";
                $zipPath = "downloads/{$zipFileName}";
                $fullZipPath = storage_path("app/{$zipPath}");

                $zip = new ZipArchive;
                if ($zip->open($fullZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new \Exception('Failed to create ZIP file');
                }

                // Add files to ZIP
                foreach ($mediaFiles as $file) {
                    $filePathInZip = $file['folder'].'/'.$file['name'];
                    $zip->addFromString($filePathInZip, $file['content']);
                }

                $zip->close();
                $fileSize = filesize($fullZipPath);
            } else {
                // For services that don't support ZIP, calculate total size
                foreach ($mediaFiles as $file) {
                    $fileSize += strlen($file['content']);
                }
            }

            // Handle cloud storage upload if destination is not device
            $cloudUploadUrl = null;
            $cloudUploadError = null;
            if ($this->destination !== 'device' && $cloudService) {
                try {
                    // Get OAuth token if not already retrieved
                    if (! $tokenData || ! isset($tokenData['access_token'])) {
                        $tokenKey = "cloud_token_{$this->destination}_{$this->token}";
                        $tokenData = \Illuminate\Support\Facades\Cache::get($tokenKey);

                        if (! $tokenData || ! isset($tokenData['access_token'])) {
                            $collectionTokenKey = "cloud_token_{$this->destination}_{$this->collectionId}";
                            $tokenData = \Illuminate\Support\Facades\Cache::get($collectionTokenKey);

                            if ($tokenData && isset($tokenData['access_token'])) {
                                \Illuminate\Support\Facades\Cache::put($tokenKey, $tokenData, now()->addHours(24));
                            }
                        }
                    }

                    if (! $tokenData || ! isset($tokenData['access_token'])) {
                        throw new \Exception('No OAuth token found for cloud storage');
                    }

                    if ($supportsZip && $fullZipPath) {
                        // Upload ZIP file for services that support it
                        $cloudUploadUrl = $this->uploadToCloudStorage($fullZipPath, $zipFileName, $this->destination, $collection->name);
                    } else {
                        // Upload individual files organized by folders/sets for services that don't support ZIP
                        $cloudUploadUrl = $cloudService->uploadFiles($mediaFiles, $tokenData['access_token'], $collection->name);
                    }

                    // Log successful upload with URL for tracking
                    if ($cloudUploadUrl) {
                        Log::info('Cloud storage upload completed successfully', [
                            'destination' => $this->destination,
                            'cloud_upload_url' => $cloudUploadUrl,
                            'collection_id' => $this->collectionId,
                            'token' => $this->token,
                        ]);
                    }
                } catch (\Exception $e) {
                    $errorMessage = $e->getMessage();
                    Log::warning('Failed to upload to cloud storage', [
                        'destination' => $this->destination,
                        'supports_zip' => $supportsZip,
                        'error' => $errorMessage,
                    ]);

                    // Store error message for frontend to display
                    $cloudUploadError = $errorMessage;

                    // Check if it's an API activation error
                    if (str_contains($errorMessage, 'not activated') || str_contains($errorMessage, 'code": 16')) {
                        $cloudUploadError = 'Google Photos API is not enabled. Please enable the Google Photos Library API in Google Cloud Console.';
                    }

                    // Continue with device download as fallback
                }
            }

            // Generate download filename
            $downloadFilename = $zipFileName ?? ($supportsZip ? "{$collection->name}-download-{$this->token}.zip" : "{$collection->name}-download-{$this->token}");

            // Update cache with completed status
            \Illuminate\Support\Facades\Cache::put("zip_download_{$this->token}", [
                'token' => $this->token,
                'collection_id' => $this->collectionId,
                'set_ids' => $this->setIds,
                'resolution' => $this->size,
                'destination' => $this->destination,
                'status' => 'completed',
                'filename' => $downloadFilename,
                'file_path' => $zipPath,
                'size' => $fileSize,
                'cloud_upload_url' => $cloudUploadUrl,
                'cloud_upload_error' => $cloudUploadError,
                'created_at' => now(),
            ], now()->addHours(24));

            // Clean up ZIP file if it was created and cloud upload succeeded
            if ($supportsZip && $fullZipPath && $cloudUploadUrl && file_exists($fullZipPath)) {
                // Keep ZIP for device downloads, only delete if cloud-only and successful
                // Actually, keep it for fallback - don't delete
            }

            // In-app notification for collection owner
            if ($collection->user_uuid) {
                try {
                    app(NotificationService::class)->create(
                        $collection->user_uuid,
                        'memora',
                        'collection_download_ready',
                        'Download ready',
                        $supportsZip
                            ? 'Your collection download is ready.'
                            : 'Your collection files are ready to upload to your cloud.',
                        null,
                        null,
                        MemoraFrontendUrls::collectionDetailPath($collection->uuid),
                        ['collection_uuid' => $collection->uuid, 'token' => $this->token]
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to create in-app notification for zip download', [
                        'collection_uuid' => $collection->uuid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Send email notification to downloader if email provided
            if ($this->email) {
                try {
                    Notification::route('mail', $this->email)
                        ->notify(new \App\Notifications\ZipDownloadReadyNotification(
                            $collection,
                            $downloadFilename,
                            $this->token
                        ));

                    // Log activity for ZIP ready email notification
                    try {
                        app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                            'notification_sent',
                            $collection,
                            'ZIP download ready email sent',
                            [
                                'channel' => 'email',
                                'notification' => 'ZipDownloadReadyNotification',
                                'recipient_email' => $this->email,
                                'collection_uuid' => $collection->uuid ?? null,
                                'download_token' => $this->token,
                            ]
                        );
                    } catch (\Throwable $logException) {
                        Log::error('Failed to log ZIP download ready notification activity', [
                            'collection_uuid' => $collection->uuid ?? null,
                            'email' => $this->email,
                            'error' => $logException->getMessage(),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to send ZIP download email to downloader', [
                        'email' => $this->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate ZIP download', [
                'token' => $this->token,
                'collection_id' => $this->collectionId,
                'error' => $e->getMessage(),
            ]);

            // Update cache with failed status
            \Illuminate\Support\Facades\Cache::put("zip_download_{$this->token}", [
                'token' => $this->token,
                'collection_id' => $this->collectionId,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'created_at' => now(),
            ], now()->addHours(24));
        }
    }

    /**
     * Upload ZIP file to cloud storage service
     */
    private function uploadToCloudStorage(string $filePath, string $fileName, string $destination, ?string $collectionName = null): ?string
    {
        try {
            // Try to get token by download token first
            $tokenKey = "cloud_token_{$destination}_{$this->token}";
            $tokenData = \Illuminate\Support\Facades\Cache::get($tokenKey);

            // If not found, try to get by collection_id (for OAuth flows where token was stored before download token was generated)
            if (! $tokenData || ! isset($tokenData['access_token'])) {
                $collectionTokenKey = "cloud_token_{$destination}_{$this->collectionId}";
                $tokenData = \Illuminate\Support\Facades\Cache::get($collectionTokenKey);

                // If found by collection_id, move it to use download token as key for future lookups
                if ($tokenData && isset($tokenData['access_token'])) {
                    \Illuminate\Support\Facades\Cache::put($tokenKey, $tokenData, now()->addHours(24));
                    Log::info('OAuth token found by collection_id, moved to download token key', [
                        'destination' => $destination,
                        'collection_id' => $this->collectionId,
                        'token' => $this->token,
                    ]);
                }
            }

            if (! $tokenData || ! isset($tokenData['access_token'])) {
                Log::warning('No OAuth token found for cloud storage', [
                    'destination' => $destination,
                    'token' => $this->token,
                    'collection_id' => $this->collectionId,
                ]);

                return null;
            }

            $cloudService = \App\Services\CloudStorage\CloudStorageFactory::make($destination);
            $accessToken = $tokenData['access_token'];

            // Check if token is expired and refresh if needed
            if (isset($tokenData['created_at'])) {
                $createdAt = \Carbon\Carbon::parse($tokenData['created_at']);
                $expiresIn = $tokenData['expires_in'] ?? 3600;

                if ($createdAt->addSeconds($expiresIn)->isPast() && isset($tokenData['refresh_token'])) {
                    try {
                        $refreshed = $cloudService->refreshToken($tokenData['refresh_token']);
                        $tokenData['access_token'] = $refreshed['access_token'];
                        $tokenData['refresh_token'] = $refreshed['refresh_token'] ?? $tokenData['refresh_token'];
                        $tokenData['expires_in'] = $refreshed['expires_in'] ?? 3600;
                        $tokenData['created_at'] = now();

                        \Illuminate\Support\Facades\Cache::put($tokenKey, $tokenData, now()->addHours(24));
                        $accessToken = $tokenData['access_token'];
                    } catch (\Exception $e) {
                        Log::warning('Failed to refresh token', [
                            'destination' => $destination,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Upload file (pass folder name for Google Drive to create folder)
            // Get collection name from the collection object if available in the job
            $collectionName = null;
            try {
                $collection = \App\Domains\Memora\Models\MemoraCollection::where('uuid', $this->collectionId)->first();
                $collectionName = $collection->name ?? null;
            } catch (\Exception $e) {
                // Collection name not available, use null
            }

            $folderName = $destination === 'googledrive' ? $collectionName : null;
            $url = $cloudService->uploadFile($filePath, $fileName, $accessToken, $folderName);

            Log::info('File uploaded to cloud storage', [
                'destination' => $destination,
                'url' => $url,
                'folder_name' => $folderName,
            ]);

            return $url;
        } catch (\Exception $e) {
            Log::error('Cloud storage upload failed', [
                'destination' => $destination,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
