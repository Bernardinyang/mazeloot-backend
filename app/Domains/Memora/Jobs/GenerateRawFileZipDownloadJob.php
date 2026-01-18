<?php

namespace App\Domains\Memora\Jobs;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraRawFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GenerateRawFileZipDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 60;

    public $timeout = 600; // 10 minutes

    public function __construct(
        public string $token,
        public string $rawFileId,
        public array $setIds,
        public ?string $email = null
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
            $rawFile = MemoraRawFile::where('uuid', $this->rawFileId)->firstOrFail();

            // Create downloads directory if it doesn't exist
            $downloadsDir = storage_path('app/downloads');
            if (! is_dir($downloadsDir)) {
                mkdir($downloadsDir, 0755, true);
            }

            // Collect all media files organized by set
            $mediaFiles = [];
            $mediaCount = 0;

            // Get all media from selected sets, organized by set
            foreach ($this->setIds as $setId) {
                $set = MemoraMediaSet::where('uuid', $setId)
                    ->where('raw_file_uuid', $this->rawFileId)
                    ->first();

                if (! $set) {
                    continue;
                }

                $setName = $this->sanitizeFolderName($set->name);

                $mediaItems = MemoraMedia::whereHas('mediaSet', function ($query) use ($setId) {
                    $query->where('uuid', $setId)->where('raw_file_uuid', $this->rawFileId);
                })->with('file')->get();

                foreach ($mediaItems as $media) {
                    $file = $media->file;
                    if (! $file) {
                        continue;
                    }

                    $filePath = $file->path;
                    $fileUrl = $file->url;

                    // Determine which file to use
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

                                // Store file data for ZIP
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

            // Create ZIP file
            $zipFileName = "{$rawFile->name}-download-{$this->token}.zip";
            $zipPath = "downloads/{$zipFileName}";
            $fullZipPath = storage_path("app/{$zipPath}");

            $zip = new ZipArchive;
            if ($zip->open($fullZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Failed to create ZIP file');
            }

            // Add files to ZIP organized by set folders
            foreach ($mediaFiles as $file) {
                $filePathInZip = $file['folder'].'/'.$file['name'];
                $zip->addFromString($filePathInZip, $file['content']);
            }

            $zip->close();
            $fileSize = filesize($fullZipPath);

            // Update cache with completed status
            \Illuminate\Support\Facades\Cache::put("raw_file_zip_download_{$this->token}", [
                'token' => $this->token,
                'raw_file_id' => $this->rawFileId,
                'set_ids' => $this->setIds,
                'status' => 'completed',
                'filename' => $zipFileName,
                'file_path' => $zipPath,
                'size' => $fileSize,
                'created_at' => now(),
            ], now()->addHours(24));
        } catch (\Exception $e) {
            Log::error('Failed to generate raw file ZIP download', [
                'token' => $this->token,
                'raw_file_id' => $this->rawFileId,
                'error' => $e->getMessage(),
            ]);

            // Update cache with failed status
            \Illuminate\Support\Facades\Cache::put("raw_file_zip_download_{$this->token}", [
                'token' => $this->token,
                'raw_file_id' => $this->rawFileId,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'created_at' => now(),
            ], now()->addHours(24));
        }
    }
}
