<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UploadRequest;
use App\Models\UserFile;
use App\Services\Upload\Exceptions\UploadException;
use App\Services\Upload\UploadService;
use App\Services\Video\VideoThumbnailGenerator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    protected UploadService $uploadService;

    protected VideoThumbnailGenerator $thumbnailGenerator;

    public function __construct(UploadService $uploadService, VideoThumbnailGenerator $thumbnailGenerator)
    {
        $this->uploadService = $uploadService;
        $this->thumbnailGenerator = $thumbnailGenerator;
    }

    /**
     * Upload a single or multiple files
     *
     * POST /api/v1/uploads
     *
     * @throws UploadException
     */
    public function upload(UploadRequest $request): JsonResponse
    {
        $options = $request->getUploadOptions();
        $userUuid = Auth::user()->uuid;

        // Handle single file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $thumbnailPath = null;
            if (str_starts_with($file->getMimeType(), 'video/')) {
                try {
                    $thumbnailPath = $this->thumbnailGenerator->generateThumbnail($file);
                } catch (\Throwable $e) {
                    Log::warning('Video thumbnail generation failed, continuing without thumbnail', ['error' => $e->getMessage()]);
                }
            }
            $thumbnailUrl = null;
            try {
                $result = $this->uploadService->upload($file, $options);
                $thumbnailUrl = $this->uploadThumbnailIfReady($thumbnailPath, $result);
            } finally {
                if ($thumbnailPath) {
                    $this->thumbnailGenerator->cleanup($thumbnailPath);
                }
            }

            $userFile = $this->storeUserFile($userUuid, $file, $result, $thumbnailUrl);

            // Include user_file UUID and thumbnail in response
            $responseData = $result->toArray();
            $responseData['userFileUuid'] = $userFile->uuid;
            if ($thumbnailUrl) {
                $responseData['thumbnail'] = $thumbnailUrl;
            }

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'file_uploaded',
                    null,
                    'File uploaded',
                    ['user_file_uuid' => $userFile->uuid],
                    $request->user(),
                    $request
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to log upload activity', ['error' => $e->getMessage()]);
            }

            return ApiResponse::success($responseData);
        }

        // Handle multiple file uploads
        if ($request->hasFile('files')) {
            $files = $request->file('files');
            if (! is_array($files)) {
                $files = [$files];
            }
            // Generate video thumbnails before upload (files are moved by upload)
            $thumbnailPaths = [];
            foreach ($files as $index => $file) {
                if (str_starts_with($file->getMimeType(), 'video/')) {
                    try {
                        $path = $this->thumbnailGenerator->generateThumbnail($file);
                        if ($path) {
                            $thumbnailPaths[$index] = $path;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Video thumbnail generation failed, continuing without thumbnail', ['error' => $e->getMessage()]);
                    }
                }
            }
            $results = $this->uploadService->uploadMultiple($files, $options);

            $data = [];
            foreach ($results as $index => $result) {
                $file = $files[$index];
                $thumbnailPath = $thumbnailPaths[$index] ?? null;
                $thumbnailUrl = $this->uploadThumbnailIfReady($thumbnailPath, $result);
                if ($thumbnailPath) {
                    $this->thumbnailGenerator->cleanup($thumbnailPath);
                }

                $userFile = $this->storeUserFile($userUuid, $file, $result, $thumbnailUrl);

                $fileData = $result->toArray();
                $fileData['userFileUuid'] = $userFile->uuid;
                if ($thumbnailUrl) {
                    $fileData['thumbnail'] = $thumbnailUrl;
                }
                $data[] = $fileData;
            }

            $userFileUuids = array_column($data, 'userFileUuid');
            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'files_uploaded',
                    null,
                    'Multiple files uploaded',
                    ['count' => count($userFileUuids), 'user_file_uuids' => $userFileUuids],
                    $request->user(),
                    $request
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to log upload activity', ['error' => $e->getMessage()]);
            }

            return ApiResponse::success($data);
        }

        // Validation should catch this, but fallback just in case
        return ApiResponse::error('No file provided', 'NO_FILE', 400);
    }

    /**
     * Upload generated thumbnail to storage and return URL.
     *
     * @param  string|null  $thumbnailPath  Local path from generateThumbnail (or null to skip)
     * @param  \App\Services\Upload\DTOs\UploadResult  $uploadResult
     * @return string|null
     */
    protected function uploadThumbnailIfReady(?string $thumbnailPath, $uploadResult): ?string
    {
        if (! $thumbnailPath) {
            return null;
        }
        try {
            $pathParts = explode('/', $uploadResult->path);
            $filename = end($pathParts);
            $videoUuid = pathinfo($filename, PATHINFO_FILENAME);
            $provider = config('upload.default_provider', 'local');
            $disk = match ($provider) {
                'local' => config('upload.providers.local.disk', 'public'),
                's3' => config('upload.providers.s3.disk', 's3'),
                'r2' => config('upload.providers.r2.disk', 'r2'),
                default => 'public',
            };

            return $this->thumbnailGenerator->uploadThumbnail($thumbnailPath, $videoUuid, $disk);
        } catch (\Exception $e) {
            Log::error('Failed to upload video thumbnail: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Store file information in user_files table
     */
    protected function storeUserFile(string $userUuid, $file, $uploadResult, ?string $thumbnailUrl = null): UserFile
    {
        // Determine file type
        $mimeType = $file->getMimeType();
        $type = 'image';
        if (str_starts_with($mimeType, 'video/')) {
            $type = 'video';
        } elseif (! str_starts_with($mimeType, 'image/')) {
            $type = 'document';
        }

        $metadata = [
            'provider' => $uploadResult->provider,
            'checksum' => $uploadResult->checksum,
        ];

        // Include thumbnail URL in metadata for videos
        if ($thumbnailUrl) {
            $metadata['thumbnail'] = $thumbnailUrl;
        }

        $userFile = UserFile::query()->create([
            'user_uuid' => $userUuid,
            'url' => $uploadResult->url,
            'path' => $uploadResult->path,
            'type' => $type,
            'filename' => $uploadResult->originalFilename,
            'mime_type' => $uploadResult->mimeType,
            'size' => $uploadResult->size,
            'width' => $uploadResult->width,
            'height' => $uploadResult->height,
            'metadata' => $metadata,
        ]);

        // Update cached storage (use file size as fallback if no variants)
        $fileSize = $uploadResult->size;
        $storageService = app(\App\Services\Storage\UserStorageService::class);
        $storageService->incrementStorage($userUuid, $fileSize);

        return $userFile;
    }
}
