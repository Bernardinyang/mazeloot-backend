<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\AddMediaFeedbackRequest;
use App\Domains\Memora\Requests\V1\MoveCopyMediaRequest;
use App\Domains\Memora\Requests\V1\RenameMediaRequest;
use App\Domains\Memora\Requests\V1\ReplaceMediaRequest;
use App\Domains\Memora\Requests\V1\UploadMediaToSetRequest;
use App\Domains\Memora\Resources\V1\MediaFeedbackResource;
use App\Domains\Memora\Resources\V1\MediaResource;
use App\Domains\Memora\Services\MediaService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function getRevisions(string $id): JsonResponse
    {
        $revisions = $this->mediaService->getRevisions($id);
        return ApiResponse::success($revisions);
    }

    public function markCompleted(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'isCompleted' => ['required', 'boolean'],
        ]);

        $media = $this->mediaService->markCompleted($id, $request->input('isCompleted'));
        return ApiResponse::success(new MediaResource($media));
    }

    /**
     * Upload media to a set
     * Uses user_file_uuid from the memora_media migration to store media
     */
    public function uploadToSet(UploadMediaToSetRequest $request, string $selectionUuid, string $setUuid): JsonResponse
    {
        $media = $this->mediaService->createFromUploadUrlForSet(
            $setUuid,
            $request->validated()
        );

        return ApiResponse::success(new MediaResource($media), 201);
    }

    /**
     * Add feedback to media in set
     */
    public function addFeedbackInSet(AddMediaFeedbackRequest $request, string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        $feedback = $this->mediaService->addFeedback($mediaId, $request->validated());

        return ApiResponse::success(new MediaFeedbackResource($feedback), 201);
    }

    public function addFeedback(AddMediaFeedbackRequest $request, string $mediaId): JsonResponse
    {
        $feedback = $this->mediaService->addFeedback($mediaId, $request->validated());

        return ApiResponse::success(new MediaFeedbackResource($feedback), 201);
    }

    /**
     * Delete media from a set
     */
    public function deleteFromSet(string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        $deleted = $this->mediaService->delete($mediaId);

        if ($deleted) {
            return ApiResponse::success([
                'message' => 'Media deleted successfully',
            ]);
        }

        return ApiResponse::error('Failed to delete media', 'DELETE_FAILED', 500);
    }

    /**
     * Rename media by updating the UserFile's filename
     */
    public function rename(RenameMediaRequest $request, string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        try {
            $validated = $request->validated();
            $media = $this->mediaService->renameMedia($mediaId, $validated['filename']);
            return ApiResponse::success(new MediaResource($media));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'RENAME_FAILED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to rename media', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to rename media', 'RENAME_FAILED', 500);
        }
    }

    /**
     * Replace media file by updating the user_file_uuid
     */
    public function replace(ReplaceMediaRequest $request, string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        try {
            $validated = $request->validated();
            $media = $this->mediaService->replaceMedia($mediaId, $validated['user_file_uuid']);
            return ApiResponse::success(new MediaResource($media));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media or file not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'REPLACE_FAILED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to replace media', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to replace media', 'REPLACE_FAILED', 500);
        }
    }

    /**
     * Move media items to a different set
     */
    public function moveToSet(MoveCopyMediaRequest $request, string $selectionId, string $setUuid): JsonResponse
    {
        try {
            $validated = $request->validated();
            $movedCount = $this->mediaService->moveMediaToSet(
                $validated['media_uuids'],
                $validated['target_set_uuid']
            );

            return ApiResponse::success([
                'message' => 'Media moved successfully',
                'moved_count' => $movedCount,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Target set not found', 'SET_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'MOVE_FAILED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to move media', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'exception' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to move media', 'MOVE_FAILED', 500);
        }
    }

    /**
     * Copy media items to a different set
     */
    public function copyToSet(MoveCopyMediaRequest $request, string $selectionId, string $setUuid): JsonResponse
    {
        try {
            $validated = $request->validated();
            $copiedMedia = $this->mediaService->copyMediaToSet(
                $validated['media_uuids'],
                $validated['target_set_uuid']
            );

            return ApiResponse::success([
                'message' => 'Media copied successfully',
                'copied_count' => count($copiedMedia),
                'media' => MediaResource::collection($copiedMedia),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Target set not found', 'SET_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'COPY_FAILED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to copy media', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'exception' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to copy media', 'COPY_FAILED', 500);
        }
    }

    /**
     * Get media for a specific set (guest access) with optional sorting
     */
    public function getSetMediaGuest(Request $request, string $id, string $setUuid): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        $sortBy = $request->query('sort_by');
        $media = $this->mediaService->getSetMedia($setUuid, $sortBy);
        return ApiResponse::success(MediaResource::collection($media));
    }

    // Guest methods

    /**
     * Get media for a specific set with optional sorting
     */
    public function getSetMedia(Request $request, string $selectionId, string $setUuid): JsonResponse
    {
        $sortBy = $request->query('sort_by');
        $media = $this->mediaService->getSetMedia($setUuid, $sortBy);
        return ApiResponse::success(MediaResource::collection($media));
    }

    /**
     * Toggle star status for a media item
     */
    public function toggleStar(string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        try {
            $result = $this->mediaService->toggleStar($mediaId);
            return ApiResponse::success($result);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to toggle star for media', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to toggle star', 'STAR_FAILED', 500);
        }
    }

    /**
     * Download original image file by media UUID
     */
    public function download(string $mediaUuid): StreamedResponse|Response|JsonResponse|RedirectResponse
    {
        try {
            Log::info('Download request started', ['media_uuid' => $mediaUuid]);
            
            $media = $this->mediaService->getMediaForDownload($mediaUuid);
            $file = $media->file;

            if (!$file) {
                Log::error('File relationship not loaded for media', [
                    'media_uuid' => $mediaUuid,
                    'media_id' => $media->id ?? null,
                    'user_file_uuid' => $media->user_file_uuid ?? null,
                ]);
                return ApiResponse::error('File not found for this media', 'FILE_NOT_FOUND', 404);
            }

            // Get the file path and URL from the UserFile model
            $filePath = $file->path;
            $fileUrl = $file->url;

            Log::info('File information', [
                'media_uuid' => $mediaUuid,
                'file_path' => $filePath,
                'file_url' => $fileUrl,
                'file_uuid' => $file->uuid ?? null,
                'filename' => $file->filename ?? null,
            ]);

            // Priority 1: If we have a cloud storage URL, download and stream it to avoid CORS issues
            if ($fileUrl && (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://'))) {
                // Check if it's a cloud storage URL
                $isCloudStorage = str_contains($fileUrl, 'amazonaws.com') || 
                    str_contains($fileUrl, 'r2.cloudflarestorage.com') ||
                    str_contains($fileUrl, 'r2.dev') ||
                    str_contains($fileUrl, 'cloudflare') ||
                    str_contains($fileUrl, 's3.') ||
                    str_contains($fileUrl, '.s3.');
                
                if ($isCloudStorage) {
                    // For cloud storage, download the file and stream it to avoid CORS issues
                    Log::info('Downloading from cloud storage URL', [
                        'media_uuid' => $mediaUuid,
                        'url' => $fileUrl,
                    ]);
                    
                    try {
                        // Download the file from the cloud storage URL
                        $fileContents = file_get_contents($fileUrl);
                        
                        if ($fileContents === false) {
                            throw new \RuntimeException('Failed to download file from cloud storage');
                        }
                        
                        // Get the filename for download
                        $filename = $file->filename ?? $media->filename ?? 'download';
                        
                        // Ensure filename has proper extension
                        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
                            // Try to get extension from mime type
                            $extension = match($file->mime_type) {
                                'image/jpeg', 'image/jpg' => 'jpg',
                                'image/png' => 'png',
                                'image/gif' => 'gif',
                                'image/webp' => 'webp',
                                'video/mp4' => 'mp4',
                                'video/mpeg' => 'mpeg',
                                default => 'bin',
                            };
                            $filename .= '.' . $extension;
                        }
                        
                        // Stream the file content as download
                        return response($fileContents)
                            ->header('Content-Type', $file->mime_type ?? 'application/octet-stream')
                            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                            ->header('Content-Length', strlen($fileContents));
                    } catch (\Exception $e) {
                        Log::error('Failed to download from cloud storage', [
                            'media_uuid' => $mediaUuid,
                            'url' => $fileUrl,
                            'error' => $e->getMessage(),
                        ]);
                        // Fall through to try other methods
                    }
                }
            }

            // Priority 2: If we have a path, try to download from storage
            if ($filePath) {
                // If it's a local storage URL, extract the path
                if ($fileUrl && str_contains($fileUrl, '/storage/')) {
                    $parsedPath = parse_url($fileUrl, PHP_URL_PATH);
                    if ($parsedPath) {
                        $filePath = str_replace('/storage/', '', $parsedPath);
                    }
                }

                // Determine which disk to use
                $disksToCheck = ['public', 'local'];
                
                // Only check cloud disks if they're configured
                try {
                    if (config('filesystems.disks.s3.key')) {
                        $disksToCheck[] = 's3';
                    }
                } catch (\Exception $e) {
                    // Ignore config errors
                }
                
                try {
                    if (config('filesystems.disks.r2.key')) {
                        $disksToCheck[] = 'r2';
                    }
                } catch (\Exception $e) {
                    // Ignore config errors
                }
                
                $foundDisk = null;
                foreach ($disksToCheck as $checkDisk) {
                    try {
                        if (Storage::disk($checkDisk)->exists($filePath)) {
                            $foundDisk = $checkDisk;
                            Log::info('File found on disk', [
                                'media_uuid' => $mediaUuid,
                                'disk' => $checkDisk,
                                'file_path' => $filePath,
                            ]);
                            break;
                        }
                    } catch (\Exception $e) {
                        // Skip this disk if there's an error (e.g., not configured)
                        Log::debug('Error checking disk', [
                            'disk' => $checkDisk,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }
                }

                if ($foundDisk) {
                    // Get the filename for download
                    $filename = $file->filename ?? $media->filename ?? 'download';
                    
                    // Ensure filename has proper extension
                    if (!pathinfo($filename, PATHINFO_EXTENSION)) {
                        // Try to get extension from mime type
                        $extension = match($file->mime_type) {
                            'image/jpeg', 'image/jpg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                            'video/mp4' => 'mp4',
                            'video/mpeg' => 'mpeg',
                            default => 'bin',
                        };
                        $filename .= '.' . $extension;
                    }

                    Log::info('Downloading file from storage', [
                        'media_uuid' => $mediaUuid,
                        'disk' => $foundDisk,
                        'file_path' => $filePath,
                        'filename' => $filename,
                    ]);

                    // Stream the file for download
                    try {
                        return Storage::disk($foundDisk)->download($filePath, $filename, [
                            'Content-Type' => $file->mime_type ?? 'application/octet-stream',
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to download file from storage', [
                            'media_uuid' => $mediaUuid,
                            'disk' => $foundDisk,
                            'file_path' => $filePath,
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        
                        // Fallback to URL if available
                        if ($fileUrl && (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://'))) {
                            try {
                                return redirect($fileUrl);
                            } catch (\Exception $redirectError) {
                                Log::error('Failed to redirect to URL', [
                                    'media_uuid' => $mediaUuid,
                                    'url' => $fileUrl,
                                    'error' => $redirectError->getMessage(),
                                ]);
                                return response()->json([
                                    'data' => ['download_url' => $fileUrl],
                                    'status' => 200,
                                    'statusText' => 'OK',
                                ], 200);
                            }
                        }
                        
                        throw $e; // Re-throw to be caught by outer catch
                    }
                }
            }

            // Priority 3: Fallback - if we have a URL but no path, try to download from URL
            if ($fileUrl && (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://'))) {
                Log::info('File not found on storage, attempting to download from URL', [
                    'media_uuid' => $mediaUuid,
                    'url' => $fileUrl,
                ]);
                
                try {
                    // Download the file from the URL
                    $fileContents = file_get_contents($fileUrl);
                    
                    if ($fileContents === false) {
                        throw new \RuntimeException('Failed to download file from URL');
                    }
                    
                    // Get the filename for download
                    $filename = $file->filename ?? $media->filename ?? 'download';
                    
                    // Ensure filename has proper extension
                    if (!pathinfo($filename, PATHINFO_EXTENSION)) {
                        // Try to get extension from mime type
                        $extension = match($file->mime_type) {
                            'image/jpeg', 'image/jpg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                            'video/mp4' => 'mp4',
                            'video/mpeg' => 'mpeg',
                            default => 'bin',
                        };
                        $filename .= '.' . $extension;
                    }
                    
                    // Stream the file content as download
                    return response($fileContents)
                        ->header('Content-Type', $file->mime_type ?? 'application/octet-stream')
                        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                        ->header('Content-Length', strlen($fileContents));
                } catch (\Exception $e) {
                    Log::error('Failed to download from URL', [
                        'media_uuid' => $mediaUuid,
                        'url' => $fileUrl,
                        'error' => $e->getMessage(),
                    ]);
                    return ApiResponse::error('Failed to download file from URL: ' . $e->getMessage(), 'DOWNLOAD_ERROR', 500);
                }
            }

            // If we get here, we can't download the file
            Log::error('Unable to download file - no path or URL available', [
                'media_uuid' => $mediaUuid,
                'file_path' => $filePath,
                'file_url' => $fileUrl,
            ]);
            return ApiResponse::error('File not available for download', 'FILE_NOT_FOUND', 404);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Media not found', [
                'media_uuid' => $mediaUuid,
                'exception' => $e->getMessage(),
            ]);
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            Log::error('Runtime error downloading media', [
                'media_uuid' => $mediaUuid,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error($e->getMessage(), 'FILE_ERROR', 500);
        } catch (\Exception $e) {
            Log::error('Failed to download media', [
                'media_uuid' => $mediaUuid,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ApiResponse::error('Failed to download file: ' . $e->getMessage(), 'DOWNLOAD_ERROR', 500);
        }
    }

}

