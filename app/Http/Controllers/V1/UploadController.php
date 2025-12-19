<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UploadRequest;
use App\Services\Upload\UploadService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

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
     */
    public function upload(UploadRequest $request): JsonResponse
    {
        $options = $request->getUploadOptions();

        // Handle single file upload
        if ($request->hasFile('file')) {
            $result = $this->uploadService->upload($request->file('file'), $options);
            return ApiResponse::success($result->toArray());
        }

        // Handle multiple file uploads
        if ($request->hasFile('files')) {
            $files = $request->file('files');
            // Ensure files is an array
            if (!is_array($files)) {
                $files = [$files];
            }
            $results = $this->uploadService->uploadMultiple($files, $options);
            
            $data = array_map(fn($result) => $result->toArray(), $results);
            return ApiResponse::success($data);
        }

        // Validation should catch this, but fallback just in case
        return ApiResponse::error('No file provided', 'NO_FILE', 400);
    }
}

