<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraCollectionDownload;
use App\Domains\Memora\Models\MemoraCollectionFavourite;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraRawFile;
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
     * Handles selections, proofing, and raw files
     */
    public function getSetMedia(Request $request, string $id, string $setUuid): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Check token type and verify it belongs to the correct phase
        $isValid = false;
        $phase = null;

        // Check for raw file token
        if (isset($guestToken->raw_file_uuid)) {
            if ($guestToken->raw_file_uuid !== $id) {
                return ApiResponse::error('Token does not match raw file', 'INVALID_TOKEN', 403);
            }
            $phase = 'rawFile';
            $isValid = true;
        }
        // Check for proofing token
        elseif (isset($guestToken->proofing_uuid)) {
            if ($guestToken->proofing_uuid !== $id) {
                return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
            }
            $phase = 'proofing';
            $isValid = true;
        }
        // Check for selection token (default)
        elseif (isset($guestToken->selection_uuid)) {
            if ($guestToken->selection_uuid !== $id) {
                return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
            }
            $phase = 'selection';
            $isValid = true;
        }

        if (! $isValid) {
            return ApiResponse::error('Invalid guest token', 'INVALID_TOKEN', 403);
        }

        // Verify phase is accessible based on type
        if ($phase === 'rawFile') {
            $rawFile = MemoraRawFile::query()->where('uuid', $id)->firstOrFail();
            if (! in_array($rawFile->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Raw file is not accessible', 'RAW_FILE_NOT_ACCESSIBLE', 403);
            }
        } elseif ($phase === 'proofing') {
            $proofing = MemoraProofing::query()->where('uuid', $id)->firstOrFail();
            if (! in_array($proofing->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Proofing is not accessible', 'PROOFING_NOT_ACCESSIBLE', 403);
            }
        } else {
            $selection = MemoraSelection::query()->where('uuid', $id)->firstOrFail();
            if (! in_array($selection->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Selection is not accessible', 'SELECTION_NOT_ACCESSIBLE', 403);
            }
        }

        $sortBy = $request->query('sort_by');
        $media = $this->mediaService->getSetMedia($setUuid, $sortBy);

        return ApiResponse::success(MediaResource::collection($media));
    }

    /**
     * Toggle selected status for a media item (protected by guest token)
     * Handles selections, proofing, and raw files
     */
    public function toggleSelected(Request $request, string $id, string $mediaId): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Check token type and verify it belongs to the correct phase
        $isValid = false;
        $phase = null;

        // Check for raw file token
        if (isset($guestToken->raw_file_uuid)) {
            if ($guestToken->raw_file_uuid !== $id) {
                return ApiResponse::error('Token does not match raw file', 'INVALID_TOKEN', 403);
            }
            $phase = 'rawFile';
            $isValid = true;
        }
        // Check for proofing token
        elseif (isset($guestToken->proofing_uuid)) {
            if ($guestToken->proofing_uuid !== $id) {
                return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
            }
            $phase = 'proofing';
            $isValid = true;
        }
        // Check for selection token (default)
        elseif (isset($guestToken->selection_uuid)) {
            if ($guestToken->selection_uuid !== $id) {
                return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
            }
            $phase = 'selection';
            $isValid = true;
        }

        if (! $isValid) {
            return ApiResponse::error('Invalid guest token', 'INVALID_TOKEN', 403);
        }

        // Verify phase is active based on type
        if ($phase === 'rawFile') {
            $rawFile = MemoraRawFile::query()->where('uuid', $id)->firstOrFail();
            if ($rawFile->status->value !== 'active') {
                return ApiResponse::error('Raw file is not active', 'RAW_FILE_NOT_ACTIVE', 403);
            }
        } elseif ($phase === 'proofing') {
            $proofing = MemoraProofing::query()->where('uuid', $id)->firstOrFail();
            if ($proofing->status->value !== 'active') {
                return ApiResponse::error('Proofing is not active', 'PROOFING_NOT_ACTIVE', 403);
            }
        } else {
            $selection = MemoraSelection::query()->where('uuid', $id)->firstOrFail();
            if ($selection->status->value !== 'active') {
                return ApiResponse::error('Selection is not active', 'SELECTION_NOT_ACTIVE', 403);
            }
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

            // Check if authenticated user is owner
            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $collection->user_uuid === $userUuid;
            }

            // Check if preview mode and owner
            $isPreviewMode = $request->query('preview') === 'true';
            $showPrivateMedia = $isOwner && $isPreviewMode;

            // Check if client is verified
            $isClientVerified = false;
            $token = $request->bearerToken() ?? $request->header('X-Guest-Token') ?? $request->query('guest_token');
            if ($token) {
                $guestToken = GuestCollectionToken::where('token', $token)
                    ->where('collection_uuid', $id)
                    ->where('expires_at', '>', now())
                    ->first();
                $isClientVerified = $guestToken !== null;
            }

            // If owner in preview mode, treat as client verified to show private media
            if ($showPrivateMedia) {
                $isClientVerified = true;
            }

            // Get media for the set
            $sortBy = $request->query('sort_by');
            $userEmail = $request->header('X-Collection-Email') ? strtolower(trim($request->header('X-Collection-Email'))) : null;
            $media = $this->mediaService->getSetMedia($setUuid, $sortBy, null, null, $id, $userEmail, $isClientVerified);

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

            // Check download limit (skip for owners)
            if (! $isOwner) {
                $limitDownloads = $downloadSettings['limitDownloads'] ?? false;
                $downloadLimit = $downloadSettings['downloadLimit'] ?? null;

                if ($limitDownloads && $downloadLimit !== null && $downloadLimit > 0) {
                    $userEmail = $request->header('X-Collection-Email');
                    $userUuid = auth()->check() ? auth()->user()->uuid : null;

                    // If restrictToContacts is enabled, each email gets their own limit
                    // Otherwise, the limit is shared across all visitors (global limit)
                    if ($restrictToContacts && $userEmail) {
                        // Per-email limit
                        $downloadCount = MemoraCollectionDownload::where('collection_uuid', $collectionId)
                            ->where('email', strtolower(trim($userEmail)))
                            ->count();
                    } else {
                        // Global limit (shared across all visitors)
                        $downloadCount = MemoraCollectionDownload::where('collection_uuid', $collectionId)
                            ->count();
                    }

                    if ($downloadCount >= $downloadLimit) {
                        $message = $restrictToContacts
                            ? "Download limit reached. You have reached the maximum of {$downloadLimit} download(s) for this collection."
                            : "Download limit reached. The collection has reached the maximum of {$downloadLimit} download(s).";

                        return ApiResponse::error($message, 'DOWNLOAD_LIMIT_EXCEEDED', 403);
                    }
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

                        // Track download
                        $this->trackDownload($request, $collectionId, $mediaId, $isOwner);

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
                        // Track download
                        $this->trackDownload($request, $collectionId, $mediaId, $isOwner);

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
                // Track download
                $this->trackDownload($request, $collectionId, $mediaId, $isOwner);

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

    /**
     * Download media from a raw file (public endpoint - validates download PIN)
     */
    public function downloadRawFileMedia(Request $request, string $rawFileId, string $mediaId): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
    {
        try {
            $rawFile = MemoraRawFile::query()
                ->where('uuid', $rawFileId)
                ->firstOrFail();

            $status = $rawFile->status?->value ?? $rawFile->status;

            // Allow access if raw file is active or completed
            if (! in_array($status, ['active', 'completed'])) {
                return ApiResponse::error('Raw file is not accessible', 'RAW_FILE_NOT_ACCESSIBLE', 403);
            }

            $settings = $rawFile->settings ?? [];
            $downloadSettings = $settings['download'] ?? [];

            // Check download PIN
            $downloadPinEnabled = $downloadSettings['downloadPinEnabled'] ?? ! empty($downloadSettings['downloadPin'] ?? null);
            $downloadPin = $downloadSettings['downloadPin'] ?? $settings['downloadPin'] ?? null;

            if ($downloadPinEnabled && $downloadPin) {
                $providedPin = $request->header('X-Download-PIN');
                if (! $providedPin || $providedPin !== $downloadPin) {
                    return ApiResponse::error('Download PIN required', 'DOWNLOAD_PIN_REQUIRED', 401);
                }
            }

            // Verify media belongs to raw file
            $media = MemoraMedia::where('uuid', $mediaId)->with('file', 'mediaSet')->firstOrFail();
            $mediaSet = $media->mediaSet;
            if (! $mediaSet || $mediaSet->raw_file_uuid !== $rawFileId) {
                return ApiResponse::error('Media does not belong to this raw file', 'MEDIA_NOT_IN_RAW_FILE', 403);
            }

            $file = $media->file;
            if (! $file) {
                return ApiResponse::error('File not found for this media', 'FILE_NOT_FOUND', 404);
            }

            $filePath = $file->path;
            $fileUrl = $file->url;

            // Download logic (same as collection)
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
                            'raw_file_id' => $rawFileId,
                            'media_id' => $mediaId,
                            'url' => $fileUrl,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Try storage disk
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
                            'raw_file_id' => $rawFileId,
                            'media_id' => $mediaId,
                            'disk' => $foundDisk,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Fallback: redirect to file URL
            if ($fileUrl) {
                return redirect($fileUrl);
            }

            return ApiResponse::error('File not available for download', 'FILE_NOT_AVAILABLE', 404);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw file or media not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to download raw file media', [
                'raw_file_id' => $rawFileId,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to download media', 'DOWNLOAD_FAILED', 500);
        }
    }

    /**
     * Track a download for limit enforcement
     */
    private function trackDownload(Request $request, string $collectionId, string $mediaId, bool $isOwner): void
    {
        // Don't track downloads for owners
        if ($isOwner) {
            return;
        }

        try {
            $userEmail = $request->header('X-Collection-Email');
            $userUuid = auth()->check() ? auth()->user()->uuid : null;

            // Determine download type (default to 'full' for now)
            // Can be extended to support 'web', 'original', 'thumbnail' based on query params or headers
            $downloadType = $request->query('type', 'full');

            MemoraCollectionDownload::create([
                'collection_uuid' => $collectionId,
                'media_uuid' => $mediaId,
                'email' => $userEmail ? strtolower(trim($userEmail)) : null,
                'user_uuid' => $userUuid,
                'download_type' => $downloadType,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Also log to activity log
            try {
                $media = MemoraMedia::find($mediaId);
                $activityLogService = app(\App\Services\ActivityLog\ActivityLogService::class);
                $activityLogService->log(
                    'downloaded',
                    $media,
                    'Downloaded media from collection',
                    [
                        'collection_uuid' => $collectionId,
                        'media_uuid' => $mediaId,
                        'email' => $userEmail ? strtolower(trim($userEmail)) : null,
                        'download_type' => $downloadType,
                    ],
                    $userUuid ? \App\Models\User::find($userUuid) : null,
                    $request
                );
            } catch (\Exception $e) {
                Log::warning('Failed to log download activity', [
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            // Log but don't fail the download if tracking fails
            Log::warning('Failed to track download', [
                'collection_id' => $collectionId,
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Toggle favourite status for collection media (public endpoint - no authentication required)
     */
    public function toggleCollectionFavourite(Request $request, string $id, string $mediaId): JsonResponse
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

            $settings = $collection->settings ?? [];
            $favoriteSettings = $settings['favorite'] ?? [];
            $favoritePhotosEnabled = $favoriteSettings['photos'] ?? $settings['favoritePhotos'] ?? true;

            // Check if favourites are enabled
            if (! $favoritePhotosEnabled) {
                return ApiResponse::error('Favourites are disabled for this collection', 'FAVOURITES_DISABLED', 403);
            }

            // Verify media belongs to collection
            $media = MemoraMedia::findOrFail($mediaId);
            $mediaSet = $media->mediaSet;
            if (! $mediaSet || $mediaSet->collection_uuid !== $id) {
                return ApiResponse::error('Media does not belong to this collection', 'MEDIA_NOT_IN_COLLECTION', 403);
            }

            // Get user info (if authenticated) or email
            $userUuid = auth()->check() ? auth()->user()->uuid : null;

            // Try multiple header name variations (Laravel may normalize headers)
            $emailHeader = $request->header('X-Collection-Email')
                ?? $request->header('x-collection-email')
                ?? $request->header('X-COLLECTION-EMAIL');

            $email = $emailHeader && trim($emailHeader) !== '' ? strtolower(trim($emailHeader)) : null;

            // If authenticated and no email header, use user's email
            if ($userUuid && ! $email && auth()->check() && auth()->user()->email) {
                $email = strtolower(trim(auth()->user()->email));
            }

            // Also try to get email from request body as fallback (preferred method)
            if (! $email) {
                $emailFromBody = $request->input('email');
                if ($emailFromBody && trim($emailFromBody) !== '') {
                    $email = strtolower(trim($emailFromBody));
                }
            }

            // Email is required for favoriting (for both guests and clients)
            if (! $userUuid && (! $email || trim($email) === '')) {
                return ApiResponse::error('Email is required to favorite media', 'EMAIL_REQUIRED', 422);
            }

            // Validate email format if provided
            if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ApiResponse::error('Invalid email format', 'INVALID_EMAIL', 422);
            }

            // Check if favourite already exists
            $query = MemoraCollectionFavourite::where('collection_uuid', $id)
                ->where('media_uuid', $mediaId);

            if ($userUuid) {
                $query->where('user_uuid', $userUuid);
            } elseif ($email) {
                $query->where('email', $email);
            } else {
                // Use IP address as fallback identifier
                $ipAddress = $request->ip();
                $query->where('ip_address', $ipAddress)
                    ->whereNull('user_uuid')
                    ->whereNull('email');
            }

            $existingFavourite = $query->first();

            if ($existingFavourite) {
                // Remove favourite
                $existingFavourite->delete();

                // Log unfavourite activity
                try {
                    $activityLogService = app(\App\Services\ActivityLog\ActivityLogService::class);
                    $activityLogService->log(
                        'unfavourited',
                        $media,
                        'Unfavourited media in collection',
                        [
                            'collection_uuid' => $id,
                            'media_uuid' => $mediaId,
                            'email' => $email,
                        ],
                        $userUuid ? \App\Models\User::find($userUuid) : null,
                        $request
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to log unfavourite activity', [
                        'error' => $e->getMessage(),
                    ]);
                }

                return ApiResponse::success([
                    'favourited' => false,
                ]);
            } else {
                // Check if notes are enabled
                $favoriteNotesEnabled = $favoriteSettings['notes'] ?? $settings['favoriteNotes'] ?? false;
                $note = null;

                if ($favoriteNotesEnabled) {
                    $request->validate([
                        'note' => ['nullable', 'string', 'max:500'],
                    ]);
                    $note = $request->input('note');
                    $note = $note ? trim($note) : null;
                    $note = $note === '' ? null : $note;
                } else {
                    // Reject note if notes are disabled
                    if ($request->has('note') && $request->input('note')) {
                        return ApiResponse::error('Notes are disabled for this collection', 'NOTES_DISABLED', 403);
                    }
                }

                // Log received values for debugging
                Log::debug('Creating favourite', [
                    'email' => $email,
                    'user_uuid' => $userUuid,
                    'note' => $note,
                    'email_header' => $request->header('X-Collection-Email'),
                    'email_from_body' => $request->input('email'),
                    'all_request_data' => $request->all(),
                    'note_input' => $request->input('note'),
                ]);

                // Ensure email is set (even if empty string, convert to null)
                $emailToSave = $email && trim($email) !== '' ? strtolower(trim($email)) : null;

                // Create favourite
                $favourite = MemoraCollectionFavourite::create([
                    'collection_uuid' => $id,
                    'media_uuid' => $mediaId,
                    'email' => $emailToSave,
                    'user_uuid' => $userUuid,
                    'note' => $note,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Verify what was saved
                $favourite->refresh();
                Log::debug('Favourite created', [
                    'saved_email' => $favourite->email,
                    'saved_note' => $favourite->note,
                    'saved_user_uuid' => $favourite->user_uuid,
                ]);

                // Log activity
                try {
                    $activityLogService = app(\App\Services\ActivityLog\ActivityLogService::class);
                    $activityLogService->log(
                        'favourite',
                        $media,
                        'Favourited media in collection',
                        [
                            'collection_uuid' => $id,
                            'media_uuid' => $mediaId,
                            'email' => $email,
                            'has_note' => ! empty($note),
                        ],
                        $userUuid ? \App\Models\User::find($userUuid) : null,
                        $request
                    );
                } catch (\Exception $e) {
                    // Don't fail if activity logging fails
                    Log::warning('Failed to log favourite activity', [
                        'error' => $e->getMessage(),
                    ]);
                }

                return ApiResponse::success([
                    'favourited' => true,
                    'note' => $note,
                ], 201);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 'VALIDATION_ERROR', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection or media not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to toggle collection favourite', [
                'collection_id' => $id,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to toggle favourite', 'FAVOURITE_FAILED', 500);
        }
    }

    /**
     * Toggle private status for collection media (public endpoint - requires client verification)
     */
    public function toggleMediaPrivate(Request $request, string $id, string $mediaId): JsonResponse
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

            $settings = $collection->settings ?? [];
            $clientExclusiveAccess = $settings['privacy']['clientExclusiveAccess'] ?? $settings['clientExclusiveAccess'] ?? false;
            $allowClientsMarkPrivate = $settings['privacy']['allowClientsMarkPrivate'] ?? $settings['allowClientsMarkPrivate'] ?? false;

            if (! $clientExclusiveAccess || ! $allowClientsMarkPrivate) {
                return ApiResponse::error('Marking photos private is not enabled for this collection', 'FEATURE_DISABLED', 403);
            }

            // Verify client password via token
            $token = $request->bearerToken() ?? $request->header('X-Guest-Token') ?? $request->query('guest_token');
            $isClientVerified = false;

            if ($token) {
                $guestToken = GuestCollectionToken::where('token', $token)
                    ->where('collection_uuid', $id)
                    ->where('expires_at', '>', now())
                    ->first();
                $isClientVerified = $guestToken !== null;
            }

            if (! $isClientVerified) {
                return ApiResponse::error('Client verification required', 'CLIENT_VERIFICATION_REQUIRED', 401);
            }

            // Verify media belongs to collection
            $media = MemoraMedia::findOrFail($mediaId);
            $mediaSet = $media->mediaSet;
            if (! $mediaSet || $mediaSet->collection_uuid !== $id) {
                return ApiResponse::error('Media does not belong to this collection', 'MEDIA_NOT_IN_COLLECTION', 403);
            }

            // Get email from request (if provided)
            $emailHeader = $request->header('X-Collection-Email')
                ?? $request->header('x-collection-email')
                ?? $request->header('X-COLLECTION-EMAIL');

            $email = $emailHeader && trim($emailHeader) !== '' ? strtolower(trim($emailHeader)) : null;

            // Also try to get email from request body
            if (! $email) {
                $emailFromBody = $request->input('email');
                if ($emailFromBody && trim($emailFromBody) !== '') {
                    $email = strtolower(trim($emailFromBody));
                }
            }

            // Email is required for marking media as private (for both guests and clients)
            if (! $email || trim($email) === '') {
                return ApiResponse::error('Email is required to mark media as private', 'EMAIL_REQUIRED', 422);
            }

            // Validate email format
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ApiResponse::error('Invalid email format', 'INVALID_EMAIL', 422);
            }

            // Toggle private status
            $isPrivate = ! $media->is_private;
            $emailLower = strtolower(trim($email));

            $media->update([
                'is_private' => $isPrivate,
                'marked_private_at' => $isPrivate ? now() : null,
                'marked_private_by_email' => $isPrivate ? $email : null,
            ]);

            if ($isPrivate) {
                // Media is now private - create/update tracking record for the email that marked it
                $existingRecord = \App\Domains\Memora\Models\MemoraCollectionPrivatePhotoAccess::where('collection_uuid', $id)
                    ->where('media_uuid', $mediaId)
                    ->where('email', $emailLower)
                    ->first();

                if ($existingRecord) {
                    // Update existing record
                    $existingRecord->ip_address = $request->ip();
                    $existingRecord->user_agent = $request->userAgent();
                    $existingRecord->touch();
                    $existingRecord->save();
                } else {
                    // Create new tracking record
                    \App\Domains\Memora\Models\MemoraCollectionPrivatePhotoAccess::create([
                        'collection_uuid' => $id,
                        'media_uuid' => $mediaId,
                        'email' => $emailLower,
                        'user_uuid' => auth()->check() ? auth()->user()->uuid : null,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                }
            } else {
                // Media is no longer private - remove tracking record for this email
                \App\Domains\Memora\Models\MemoraCollectionPrivatePhotoAccess::where('collection_uuid', $id)
                    ->where('media_uuid', $mediaId)
                    ->where('email', $emailLower)
                    ->delete();
            }

            return ApiResponse::success(new MediaResource($media->fresh()));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection or media not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to toggle media private status', [
                'collection_id' => $id,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to toggle private status', 'TOGGLE_FAILED', 500);
        }
    }
}
