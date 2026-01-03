<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Requests\V1\AddMediaFeedbackRequest;
use App\Domains\Memora\Resources\V1\MediaFeedbackResource;
use App\Domains\Memora\Resources\V1\MediaResource;
use App\Domains\Memora\Services\MediaService;
use App\Http\Controllers\Controller;
use App\Models\GuestCollectionToken;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public Media Controller
 *
 * Handles public/guest access to media.
 * These endpoints are protected by guest token middleware (not user authentication).
 * Users must generate a guest token before accessing these routes.
 */
class PublicMediaController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Get media for a specific set (protected by guest token) with optional sorting
     */
    public function getSetMedia(Request $request, string $id, string $setUuid): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        // Allow access if selection status is 'active' or 'completed' (view-only for completed)
        $selection = MemoraSelection::query()->where('uuid', $id)->firstOrFail();
        if (! in_array($selection->status->value, ['active', 'completed'])) {
            return ApiResponse::error('Selection is not accessible', 'SELECTION_NOT_ACCESSIBLE', 403);
        }

        $sortBy = $request->query('sort_by');
        $media = $this->mediaService->getSetMedia($setUuid, $sortBy);

        return ApiResponse::success(MediaResource::collection($media));
    }

    /**
     * Toggle selected status for a media item (protected by guest token)
     */
    public function toggleSelected(Request $request, string $id, string $mediaId): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        // Verify selection is active
        $selection = MemoraSelection::query()->where('uuid', $id)->firstOrFail();
        if ($selection->status->value !== 'active') {
            return ApiResponse::error('Selection is not active', 'SELECTION_NOT_ACTIVE', 403);
        }

        try {
            // Get current selected status
            $media = \App\Domains\Memora\Models\MemoraMedia::findOrFail($mediaId);
            $isSelected = ! $media->is_selected;

            // Toggle selected status
            $updatedMedia = $this->mediaService->markSelected($mediaId, $isSelected);

            return ApiResponse::success(new MediaResource($updatedMedia));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'SELECTION_LIMIT_REACHED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to toggle selected status for media', [
                'selection_id' => $id,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to toggle selected status', 'TOGGLE_FAILED', 500);
        }
    }

    /**
     * Get media for a specific proofing set (protected by guest token) with optional sorting
     */
    public function getProofingSetMedia(Request $request, string $id, string $setUuid): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        // Allow access if proofing status is 'active' or 'completed' (view-only for completed)
        $proofing = MemoraProofing::query()->where('uuid', $id)->firstOrFail();
        if (! in_array($proofing->status->value, ['active', 'completed'])) {
            return ApiResponse::error('Proofing is not accessible', 'PROOFING_NOT_ACCESSIBLE', 403);
        }

        $sortBy = $request->query('sort_by');
        $media = $this->mediaService->getSetMedia($setUuid, $sortBy);

        return ApiResponse::success(MediaResource::collection($media));
    }

    /**
     * Toggle selected status for a proofing media item (protected by guest token)
     */
    public function toggleProofingSelected(Request $request, string $id, string $mediaId): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        // Verify proofing is active
        $proofing = MemoraProofing::query()->where('uuid', $id)->firstOrFail();
        if ($proofing->status->value !== 'active') {
            return ApiResponse::error('Proofing is not active', 'PROOFING_NOT_ACTIVE', 403);
        }

        try {
            // Get current selected status
            $media = \App\Domains\Memora\Models\MemoraMedia::findOrFail($mediaId);
            $isSelected = ! $media->is_selected;

            // Toggle selected status
            $updatedMedia = $this->mediaService->markSelected($mediaId, $isSelected);

            return ApiResponse::success(new MediaResource($updatedMedia));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'SELECTION_LIMIT_REACHED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to toggle selected status for proofing media', [
                'proofing_id' => $id,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to toggle selected status', 'TOGGLE_FAILED', 500);
        }
    }

    /**
     * Add feedback to proofing media (protected by guest token)
     */
    public function addProofingFeedback(AddMediaFeedbackRequest $request, string $id, string $setId, string $mediaId): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        // Verify proofing is active or completed (allow comments on completed proofing)
        $proofing = MemoraProofing::query()->where('uuid', $id)->firstOrFail();
        if (! in_array($proofing->status->value, ['active', 'completed'])) {
            return ApiResponse::error('Proofing is not accessible', 'PROOFING_NOT_ACCESSIBLE', 403);
        }

        // Verify media belongs to this proofing
        $media = MemoraMedia::findOrFail($mediaId);
        $mediaSet = $media->mediaSet;
        if (! $mediaSet || $mediaSet->proof_uuid !== $id) {
            return ApiResponse::error('Media does not belong to this proofing', 'MEDIA_NOT_IN_PROOFING', 403);
        }

        try {
            $feedback = $this->mediaService->addFeedback($mediaId, $request->validated());

            return ApiResponse::success(new MediaFeedbackResource($feedback), 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to add feedback to proofing media', [
                'proofing_id' => $id,
                'set_id' => $setId,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to add feedback', 'FEEDBACK_FAILED', 500);
        }
    }

    /**
     * Update feedback for proofing media (protected by guest token)
     */
    public function updateProofingFeedback(Request $request, string $id, string $setId, string $mediaId, string $feedbackId): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        $request->validate([
            'content' => ['required', 'string'],
        ]);

        // Verify feedback belongs to media that belongs to this proofing
        $feedback = \App\Domains\Memora\Models\MemoraMediaFeedback::findOrFail($feedbackId);
        $media = $feedback->media;
        $mediaSet = $media->mediaSet;
        if (! $mediaSet || $mediaSet->proof_uuid !== $id) {
            return ApiResponse::error('Feedback does not belong to this proofing', 'FEEDBACK_NOT_IN_PROOFING', 403);
        }

        // Verify media belongs to this proofing
        if ($media->uuid !== $mediaId) {
            return ApiResponse::error('Feedback does not belong to this media', 'FEEDBACK_NOT_IN_MEDIA', 403);
        }

        try {
            $feedback = $this->mediaService->updateFeedback($feedbackId, $request->only('content'));

            return ApiResponse::success(new MediaFeedbackResource($feedback));
        } catch (\Exception $e) {
            Log::error('Failed to update feedback', [
                'feedback_id' => $feedbackId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error($e->getMessage(), 'UPDATE_FAILED', 400);
        }
    }

    /**
     * Delete feedback for proofing media (protected by guest token)
     */
    public function deleteProofingFeedback(Request $request, string $id, string $setId, string $mediaId, string $feedbackId): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        // Verify feedback belongs to media that belongs to this proofing
        $feedback = \App\Domains\Memora\Models\MemoraMediaFeedback::findOrFail($feedbackId);
        $media = $feedback->media;
        $mediaSet = $media->mediaSet;
        if (! $mediaSet || $mediaSet->proof_uuid !== $id) {
            return ApiResponse::error('Feedback does not belong to this proofing', 'FEEDBACK_NOT_IN_PROOFING', 403);
        }

        // Verify media belongs to this proofing
        if ($media->uuid !== $mediaId) {
            return ApiResponse::error('Feedback does not belong to this media', 'FEEDBACK_NOT_IN_MEDIA', 403);
        }

        try {
            $deleted = $this->mediaService->deleteFeedback($feedbackId);
            if ($deleted) {
                return ApiResponse::success(['message' => 'Feedback deleted successfully']);
            }

            return ApiResponse::error('Failed to delete feedback', 'DELETE_FAILED', 500);
        } catch (\Exception $e) {
            Log::error('Failed to delete feedback', [
                'feedback_id' => $feedbackId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error($e->getMessage(), 'DELETE_FAILED', 400);
        }
    }

    /**
     * Approve media for proofing (protected by guest token)
     * Marks media as completed/approved
     */
    public function approveProofingMedia(Request $request, string $id, string $mediaId): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        // Verify proofing is active
        $proofing = MemoraProofing::query()->where('uuid', $id)->firstOrFail();
        if ($proofing->status->value !== 'active') {
            return ApiResponse::error('Proofing is not active', 'PROOFING_NOT_ACTIVE', 403);
        }

        // Verify media belongs to this proofing
        $media = MemoraMedia::findOrFail($mediaId);
        $mediaSet = $media->mediaSet;
        if (! $mediaSet || $mediaSet->proof_uuid !== $id) {
            return ApiResponse::error('Media does not belong to this proofing', 'MEDIA_NOT_IN_PROOFING', 403);
        }

        // Block approval if media is already rejected
        if ($media->is_rejected) {
            return ApiResponse::error('Cannot approve rejected media', 'MEDIA_REJECTED', 403);
        }

        try {
            // Mark media as completed/approved
            $updatedMedia = $this->mediaService->markCompleted($mediaId, true);

            Log::info('Media approved for proofing', [
                'proofing_id' => $id,
                'media_id' => $mediaId,
                'guest_email' => $guestToken->email ?? null,
            ]);

            return ApiResponse::success(new MediaResource($updatedMedia));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to approve proofing media', [
                'proofing_id' => $id,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to approve media', 'APPROVE_FAILED', 500);
        }
    }

    /**
     * Get revisions for a media item (protected by guest token)
     */
    public function getRevisions(Request $request, string $mediaId): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify guest token exists
        if (! $guestToken) {
            return ApiResponse::error('Guest token is required', 'GUEST_TOKEN_MISSING', 401);
        }

        try {
            $media = \App\Domains\Memora\Models\MemoraMedia::findOrFail($mediaId);

            // Verify media belongs to proofing associated with guest token
            $mediaSet = $media->mediaSet;
            if (! $mediaSet) {
                return ApiResponse::error('Media does not belong to any set', 'INVALID_MEDIA', 404);
            }

            $proofing = $mediaSet->proofing;
            if (! $proofing) {
                return ApiResponse::error('Media set does not belong to any proofing', 'INVALID_MEDIA', 404);
            }

            // Verify token matches proofing
            if ($proofing->uuid !== $guestToken->proofing_uuid) {
                return ApiResponse::error('Media does not belong to this proofing', 'UNAUTHORIZED', 403);
            }

            // Allow access if proofing status is 'active' or 'completed'
            if (! in_array($proofing->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Proofing is not accessible', 'PROOFING_NOT_ACCESSIBLE', 403);
            }

            $revisions = $this->mediaService->getRevisions($mediaId);

            return ApiResponse::success($revisions);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to get media revisions', [
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to get revisions', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Get media for a specific collection set (public endpoint - no authentication required)
     */
    public function getCollectionSetMedia(Request $request, string $id, string $setUuid): JsonResponse
    {
        try {
            // Verify collection exists and is published
            $collection = MemoraCollection::query()
                ->where('uuid', $id)
                ->firstOrFail();

            $status = $collection->status?->value ?? $collection->status;

            // Allow access if collection is active (published)
            if ($status !== 'active') {
                return ApiResponse::error('Collection is not accessible', 'COLLECTION_NOT_ACCESSIBLE', 403);
            }

            // Verify set belongs to collection
            $set = \App\Domains\Memora\Models\MemoraMediaSet::where('uuid', $setUuid)
                ->where('collection_uuid', $id)
                ->firstOrFail();

            // Get media for the set
            $sortBy = $request->query('sort_by');
            $media = $this->mediaService->getSetMedia($setUuid, $sortBy);

            return ApiResponse::success(MediaResource::collection($media));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection or set not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch collection set media', [
                'collection_id' => $id,
                'set_id' => $setUuid,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch media', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Download media from a collection (public endpoint - no authentication required)
     * Validates download PIN, email restrictions, and downloadable sets
     */
    public function downloadCollectionMedia(Request $request, string $collectionId, string $mediaId): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
    {
        try {
            // Verify collection exists and is published
            $collection = MemoraCollection::query()
                ->where('uuid', $collectionId)
                ->firstOrFail();

            $status = $collection->status?->value ?? $collection->status;

            // Allow access if collection is active (published)
            if ($status !== 'active') {
                return ApiResponse::error('Collection is not accessible', 'COLLECTION_NOT_ACCESSIBLE', 403);
            }

            $settings = $collection->settings ?? [];

            // Check password protection if required
            $hasPasswordProtection = ! empty($settings['privacy']['collectionPasswordEnabled'] ?? $settings['privacy']['password'] ?? $settings['password'] ?? false);
            $password = $settings['privacy']['password'] ?? $settings['password'] ?? null;

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $collection->user_uuid === $userUuid;
            }

            if ($hasPasswordProtection && $password && ! $isOwner) {
                // Check for guest token first
                $token = $request->bearerToken() ?? $request->header('X-Guest-Token') ?? $request->query('guest_token');
                $guestToken = null;

                if ($token) {
                    $guestToken = GuestCollectionToken::where('token', $token)
                        ->where('collection_uuid', $collectionId)
                        ->where('expires_at', '>', now())
                        ->first();
                }

                // If no valid guest token, check password header
                if (! $guestToken) {
                    $providedPassword = $request->header('X-Collection-Password');
                    if (! $providedPassword || $providedPassword !== $password) {
                        return ApiResponse::error('Password required', 'PASSWORD_REQUIRED', 401);
                    }
                }
            }

            $downloadSettings = $settings['download'] ?? [];

            // Check if downloads are enabled
            if (! ($downloadSettings['photoDownload'] ?? true)) {
                return ApiResponse::error('Downloads are disabled for this collection', 'DOWNLOADS_DISABLED', 403);
            }

            // Check download PIN
            $downloadPinEnabled = $downloadSettings['downloadPinEnabled'] ?? false;
            $downloadPin = $downloadSettings['downloadPin'] ?? null;
            if ($downloadPinEnabled && $downloadPin) {
                $providedPin = $request->header('X-Download-PIN');
                if (! $providedPin || $providedPin !== $downloadPin) {
                    return ApiResponse::error('Download PIN required', 'DOWNLOAD_PIN_REQUIRED', 401);
                }
            }

            // Check email restrictions
            $restrictToContacts = $downloadSettings['restrictToContacts'] ?? false;
            $allowedEmails = $downloadSettings['allowedDownloadEmails'] ?? null;
            if ($restrictToContacts && $allowedEmails && is_array($allowedEmails) && count($allowedEmails) > 0) {
                $userEmail = $request->header('X-Collection-Email');
                if (! $userEmail) {
                    return ApiResponse::error('Email registration required for downloads', 'EMAIL_REQUIRED', 401);
                }
                $emailLower = strtolower(trim($userEmail));
                $isEmailAllowed = false;
                foreach ($allowedEmails as $allowedEmail) {
                    if ($allowedEmail && strtolower(trim($allowedEmail)) === $emailLower) {
                        $isEmailAllowed = true;
                        break;
                    }
                }
                if (! $isEmailAllowed) {
                    return ApiResponse::error('Your email is not authorized to download from this collection', 'EMAIL_NOT_AUTHORIZED', 403);
                }
            }

            // Verify media belongs to collection
            $media = MemoraMedia::findOrFail($mediaId);
            $mediaSet = $media->mediaSet;
            if (! $mediaSet || $mediaSet->collection_uuid !== $collectionId) {
                return ApiResponse::error('Media does not belong to this collection', 'MEDIA_NOT_IN_COLLECTION', 403);
            }

            // Check downloadable sets restriction
            $downloadableSets = $downloadSettings['downloadableSets'] ?? null;
            if ($downloadableSets && is_array($downloadableSets) && count($downloadableSets) > 0) {
                if (! in_array($mediaSet->uuid, $downloadableSets)) {
                    return ApiResponse::error('This set is not available for download', 'SET_NOT_DOWNLOADABLE', 403);
                }
            }

            // Get media for download (public access - no ownership check)
            $media = MemoraMedia::where('uuid', $mediaId)
                ->with('file')
                ->firstOrFail();
            $file = $media->file;

            if (! $file) {
                return ApiResponse::error('File not found for this media', 'FILE_NOT_FOUND', 404);
            }

            // Get the file path and URL from the UserFile model
            $filePath = $file->path;
            $fileUrl = $file->url;

            // Priority 1: If we have a cloud storage URL, download and stream it
            if ($fileUrl && (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://'))) {
                $isCloudStorage = str_contains($fileUrl, 'amazonaws.com') ||
                    str_contains($fileUrl, 'r2.cloudflarestorage.com') ||
                    str_contains($fileUrl, 'r2.dev') ||
                    str_contains($fileUrl, 'cloudflare') ||
                    str_contains($fileUrl, 's3.') ||
                    str_contains($fileUrl, '.s3.');

                if ($isCloudStorage) {
                    try {
                        $fileContents = file_get_contents($fileUrl);
                        if ($fileContents === false) {
                            throw new \RuntimeException('Failed to download file from cloud storage');
                        }

                        $filename = $file->filename ?? 'download';
                        if (! pathinfo($filename, PATHINFO_EXTENSION)) {
                            $extension = match ($file->mime_type) {
                                'image/jpeg', 'image/jpg' => 'jpg',
                                'image/png' => 'png',
                                'image/gif' => 'gif',
                                'image/webp' => 'webp',
                                'video/mp4' => 'mp4',
                                'video/mpeg' => 'mpeg',
                                default => 'bin',
                            };
                            $filename .= '.'.$extension;
                        }

                        return response($fileContents)
                            ->header('Content-Type', $file->mime_type ?? 'application/octet-stream')
                            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
                            ->header('Content-Length', strlen($fileContents));
                    } catch (\Exception $e) {
                        Log::error('Failed to download from cloud storage', [
                            'media_id' => $mediaId,
                            'url' => $fileUrl,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Priority 2: If we have a path, try to download from storage
            if ($filePath) {
                $disks = ['s3', 'r2', 'local'];
                $foundDisk = null;

                foreach ($disks as $disk) {
                    if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($filePath)) {
                        $foundDisk = $disk;
                        break;
                    }
                }

                if ($foundDisk) {
                    $filename = $file->filename ?? 'download';
                    if (! pathinfo($filename, PATHINFO_EXTENSION)) {
                        $extension = match ($file->mime_type) {
                            'image/jpeg', 'image/jpg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                            'video/mp4' => 'mp4',
                            'video/mpeg' => 'mpeg',
                            default => 'bin',
                        };
                        $filename .= '.'.$extension;
                    }

                    try {
                        return \Illuminate\Support\Facades\Storage::disk($foundDisk)->download($filePath, $filename, [
                            'Content-Type' => $file->mime_type ?? 'application/octet-stream',
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to download from storage', [
                            'media_id' => $mediaId,
                            'disk' => $foundDisk,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Fallback: redirect to file URL if available
            if ($fileUrl) {
                return redirect($fileUrl);
            }

            return ApiResponse::error('File not available for download', 'FILE_NOT_AVAILABLE', 404);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection or media not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to download collection media', [
                'collection_id' => $collectionId,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to download media', 'DOWNLOAD_FAILED', 500);
        }
    }
}
