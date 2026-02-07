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
            $result = $this->uploadService->upload($file, $options);

            // Generate thumbnail for videos
            $thumbnailUrl = null;
            if (str_starts_with($file->getMimeType(), 'video/')) {
                $thumbnailUrl = $this->generateVideoThumbnail($file, $result);
            }

            // Store file information in user_files table
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
            // Ensure files is an array
            if (! is_array($files)) {
                $files = [$files];
            }
            $results = $this->uploadService->uploadMultiple($files, $options);

            $data = [];
            foreach ($results as $index => $result) {
                $file = $files[$index];

                // Generate thumbnail for videos
                $thumbnailUrl = null;
                if (str_starts_with($file->getMimeType(), 'video/')) {
                    $thumbnailUrl = $this->generateVideoThumbnail($file, $result);
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
     * Generate thumbnail for video file
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  \App\Services\Upload\DTOs\UploadResult  $uploadResult
     * @return string|null Thumbnail URL or null if failed
     */
    protected function generateVideoThumbnail($file, $uploadResult): ?string
    {
        try {
            // Generate thumbnail
            $thumbnailPath = $this->thumbnailGenerator->generateThumbnail($file);

            if (! $thumbnailPath) {
                return null;
            }

            // Extract UUID from path or generate one
            // The path format is usually: uploads/YYYY/MM/DD/{uuid}.ext
            $pathParts = explode('/', $uploadResult->path);
            $filename = end($pathParts);
            $videoUuid = pathinfo($filename, PATHINFO_FILENAME);

            // Determine storage disk based on provider
            $provider = config('upload.default_provider', 'local');
            $disk = match ($provider) {
                'local' => config('upload.providers.local.disk', 'public'),
                's3' => config('upload.providers.s3.disk', 's3'),
                'r2' => config('upload.providers.r2.disk', 'r2'),
                default => 'public',
            };

            // Upload thumbnail to storage
            $thumbnailUrl = $this->thumbnailGenerator->uploadThumbnail($thumbnailPath, $videoUuid, $disk);

            // Cleanup temporary thumbnail
            $this->thumbnailGenerator->cleanup($thumbnailPath);

            return $thumbnailUrl;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate video thumbnail: '.$e->getMessage());

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
