<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ImageUploadRequest;
use App\Models\UserFile;
use App\Services\Image\ImageUploadService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ImageUploadController extends Controller
{
    protected ImageUploadService $imageUploadService;

    public function __construct(ImageUploadService $imageUploadService)
    {
        $this->imageUploadService = $imageUploadService;
    }

    /**
     * Upload single or multiple images with automatic variants
     *
     * POST /api/v1/images/upload
     * Supports both 'file' (single) and 'files[]' (multiple) for compatibility
     */
    public function upload(ImageUploadRequest $request): JsonResponse
    {
        // Support both 'file' (single) and 'files' (array) for compatibility
        $files = $request->hasFile('files')
            ? $request->file('files')
            : ($request->hasFile('file') ? [$request->file('file')] : []);

        if (empty($files)) {
            return ApiResponse::error('No files provided', 'NO_FILES', 400);
        }

        // Ensure files is an array
        if (! is_array($files)) {
            $files = [$files];
        }

        $options = [
            'context' => $request->input('context'),
            'visibility' => $request->input('visibility', 'public'),
        ];

        $uploadResults = $this->imageUploadService->uploadMultipleImages($files, $options);
        $userUuid = Auth::user()->uuid;

        // Store files in user_files table and format response
        $results = [];
        foreach ($uploadResults as $index => $uploadResult) {
            $file = $files[$index];

            // Store file information in user_files table
            $userFile = $this->storeUserFile($userUuid, $file, $uploadResult);

            // Format response to match UploadController format, but include variants
            $results[] = [
                'url' => $uploadResult['variants']['original'] ?? $uploadResult['variants']['large'] ?? '',
                'path' => 'uploads/images/'.$uploadResult['uuid'],
                'originalFilename' => $file->getClientOriginalName(),
                'mimeType' => $file->getMimeType(),
                'size' => $uploadResult['meta']['size'],
                'width' => $uploadResult['meta']['width'],
                'height' => $uploadResult['meta']['height'],
                'userFileUuid' => $userFile->uuid,
                'variants' => $uploadResult['variants'], // Include all variants
                'uuid' => $uploadResult['uuid'],
            ];
        }

        // Return single object if single file, array if multiple
        return ApiResponse::success(count($results) === 1 ? $results[0] : $results);
    }

    /**
     * Store file information in user_files table
     */
    protected function storeUserFile(string $userUuid, $file, array $uploadResult): UserFile
    {
        // Use original variant URL as the primary URL
        $primaryUrl = $uploadResult['variants']['original']
            ?? $uploadResult['variants']['large']
            ?? '';

        return UserFile::query()->create([
            'user_uuid' => $userUuid,
            'url' => $primaryUrl,
            'path' => 'uploads/images/'.$uploadResult['uuid'],
            'type' => 'image',
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $uploadResult['meta']['size'],
            'width' => $uploadResult['meta']['width'],
            'height' => $uploadResult['meta']['height'],
            'metadata' => [
                'uuid' => $uploadResult['uuid'],
                'variants' => $uploadResult['variants'],
                'provider' => config('upload.default_provider', 'local'),
            ],
        ]);
    }
}
