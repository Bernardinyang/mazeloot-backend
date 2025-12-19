<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UploadRequest;
use App\Models\UserFile;
use App\Services\Upload\Exceptions\UploadException;
use App\Services\Upload\UploadService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UploadController extends Controller
{
    protected UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Upload a single or multiple files
     *
     * POST /api/v1/uploads
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

            // Store file information in user_files table
            $userFile = $this->storeUserFile($userUuid, $file, $result);

            // Include user_file UUID in response
            $responseData = $result->toArray();
            $responseData['userFileUuid'] = $userFile->uuid;

            return ApiResponse::success($responseData);
        }

        // Handle multiple file uploads
        if ($request->hasFile('files')) {
            $files = $request->file('files');
            // Ensure files is an array
            if (!is_array($files)) {
                $files = [$files];
            }
            $results = $this->uploadService->uploadMultiple($files, $options);

            $data = [];
            foreach ($results as $index => $result) {
                $file = $files[$index];
                $userFile = $this->storeUserFile($userUuid, $file, $result);

                $fileData = $result->toArray();
                $fileData['userFileUuid'] = $userFile->uuid;
                $data[] = $fileData;
            }

            return ApiResponse::success($data);
        }

        // Validation should catch this, but fallback just in case
        return ApiResponse::error('No file provided', 'NO_FILE', 400);
    }

    /**
     * Store file information in user_files table
     */
    protected function storeUserFile(string $userUuid, $file, $uploadResult): UserFile
    {
        // Determine file type
        $mimeType = $file->getMimeType();
        $type = 'image';
        if (str_starts_with($mimeType, 'video/')) {
            $type = 'video';
        } elseif (!str_starts_with($mimeType, 'image/')) {
            $type = 'document';
        }

        return UserFile::query()->create([
            'user_uuid' => $userUuid,
            'url' => $uploadResult->url,
            'path' => $uploadResult->path,
            'type' => $type,
            'filename' => $uploadResult->originalFilename,
            'mime_type' => $uploadResult->mimeType,
            'size' => $uploadResult->size,
            'width' => $uploadResult->width,
            'height' => $uploadResult->height,
            'metadata' => [
                'provider' => $uploadResult->provider,
                'checksum' => $uploadResult->checksum,
            ],
        ]);
    }
}

