<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraClosureRequest;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Services\ClosureRequestService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ClosureRequestController extends Controller
{
    protected ClosureRequestService $closureRequestService;

    public function __construct(ClosureRequestService $closureRequestService)
    {
        $this->closureRequestService = $closureRequestService;
    }

    /**
     * Create a closure request (authenticated - creative only)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'proofing_id' => ['required', 'string'],
            'media_id' => ['required', 'string'],
            'todos' => ['required', 'array'],
            'todos.*.text' => ['required', 'string'],
            'todos.*.completed' => ['boolean'],
        ]);

        try {
            $closureRequest = $this->closureRequestService->create(
                proofingId: $request->input('proofing_id'),
                mediaId: $request->input('media_id'),
                todos: $request->input('todos'),
                userId: Auth::user()->uuid
            );

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'closure_request_created',
                    $closureRequest,
                    'Closure request created',
                    ['closure_request_uuid' => $closureRequest->uuid, 'proofing_id' => $request->input('proofing_id'), 'media_id' => $request->input('media_id')],
                    $request->user(),
                    $request
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to log closure request activity', ['error' => $e->getMessage()]);
            }

            return ApiResponse::success([
                'closure_request' => [
                    'uuid' => $closureRequest->uuid,
                    'token' => $closureRequest->token,
                    'url' => $this->closureRequestService->getPublicUrl($closureRequest->token),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create closure request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to create closure request: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get closure request by token (public - no auth required)
     */
    public function showByToken(string $token): JsonResponse
    {
        try {
            $closureRequest = $this->closureRequestService->findByToken($token);

            if (! $closureRequest) {
                return ApiResponse::error('Closure request not found', 404);
            }

            $closureRequest->load('user');

            return ApiResponse::success([
                'closure_request' => [
                    'uuid' => $closureRequest->uuid,
                    'token' => $closureRequest->token,
                    'status' => $closureRequest->status,
                    'todos' => $closureRequest->todos,
                    'proofing' => [
                        'uuid' => $closureRequest->proofing->uuid,
                        'name' => $closureRequest->proofing->name,
                        'primary_email' => $closureRequest->proofing->primary_email,
                        'allowed_emails' => $closureRequest->proofing->allowed_emails,
                        'has_password' => ! empty($closureRequest->proofing->password),
                        'project_uuid' => $closureRequest->proofing->project_uuid,
                        'branding_domain' => \App\Support\MemoraFrontendUrls::getBrandingDomainForUser($closureRequest->proofing->user_uuid),
                    ],
                    'creative_email' => $closureRequest->user->email ?? null,
                    'media' => [
                        'uuid' => $closureRequest->media->uuid,
                        'revision_number' => $closureRequest->media->revision_number,
                        'file' => $this->fileViewUrlOrNull($closureRequest->media->file),
                    ],
                    'comments' => $this->closureRequestService->getMediaComments($closureRequest->media_uuid),
                    'created_at' => $closureRequest->created_at,
                    'rejection_reason' => $closureRequest->rejection_reason,
                    'rejected_by_email' => $closureRequest->rejected_by_email,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch closure request', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch closure request', 500);
        }
    }

    /**
     * Approve closure request (public - no auth required, but requires email verification)
     */
    public function approve(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $closureRequest = $this->closureRequestService->approve(
                token: $token,
                email: $request->input('email')
            );

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'closure_request_approved',
                    $closureRequest,
                    'Closure request approved',
                    ['closure_request_uuid' => $closureRequest->uuid],
                    null,
                    $request
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to log closure request activity', ['error' => $e->getMessage()]);
            }

            return ApiResponse::success([
                'closure_request' => [
                    'uuid' => $closureRequest->uuid,
                    'status' => $closureRequest->status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to approve closure request', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to approve closure request: '.$e->getMessage(), 500);
        }
    }

    /**
     * Reject closure request (public - no auth required, but requires email verification)
     */
    public function reject(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $closureRequest = $this->closureRequestService->reject(
                token: $token,
                email: $request->input('email'),
                reason: $request->input('reason')
            );

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'closure_request_rejected',
                    $closureRequest,
                    'Closure request rejected',
                    ['closure_request_uuid' => $closureRequest->uuid],
                    null,
                    $request
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to log closure request activity', ['error' => $e->getMessage()]);
            }

            return ApiResponse::success([
                'closure_request' => [
                    'uuid' => $closureRequest->uuid,
                    'status' => $closureRequest->status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reject closure request', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to reject closure request: '.$e->getMessage(), 500);
        }
    }

    /**
     * Resend closure request email to client (authenticated - creative only).
     */
    public function resend(Request $request, string $uuid): JsonResponse
    {
        try {
            $this->closureRequestService->resendNotification($uuid, Auth::user()->uuid);

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'closure_request_resent',
                    null,
                    'Closure request email resent',
                    ['closure_request_uuid' => $uuid],
                    $request->user(),
                    $request
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to log closure request activity', ['error' => $e->getMessage()]);
            }

            return ApiResponse::success([
                'message' => 'Closure request email resent to client.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resend closure request', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Cancel a pending closure request (authenticated - creative only).
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        try {
            $this->closureRequestService->cancel($uuid, Auth::user()->uuid);

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'closure_request_cancelled',
                    null,
                    'Closure request cancelled',
                    ['closure_request_uuid' => $uuid],
                    $request->user(),
                    $request
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to log closure request activity', ['error' => $e->getMessage()]);
            }

            return ApiResponse::success([
                'message' => 'Closure request cancelled.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel closure request', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Get closure requests for a media item (authenticated - creative only)
     */
    public function getByMedia(string $mediaId): JsonResponse
    {
        try {
            $closureRequests = $this->closureRequestService->getByMedia($mediaId, Auth::id());

            return ApiResponse::success([
                'closure_requests' => $closureRequests,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch closure requests', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch closure requests: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get closure requests for a media item (public - guest token required)
     */
    public function getByMediaPublic(Request $request, string $mediaId): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify guest token exists
        if (! $guestToken) {
            return ApiResponse::error('Guest token is required', 'GUEST_TOKEN_MISSING', 401);
        }

        try {
            $media = MemoraMedia::findOrFail($mediaId);

            // Verify media belongs to proofing associated with guest token
            $mediaSet = $media->mediaSet;
            if (! $mediaSet) {
                return ApiResponse::error('Media does not belong to any set', 'INVALID_MEDIA', 404);
            }

            $proofing = $mediaSet->proofing;
            if (! $proofing || $proofing->uuid !== $guestToken->proofing_uuid) {
                return ApiResponse::error('Media does not belong to this proofing', 'UNAUTHORIZED', 403);
            }

            $closureRequests = MemoraClosureRequest::where('media_uuid', $mediaId)
                ->orderBy('created_at', 'desc')
                ->get();

            $closureRequestsData = $closureRequests->map(function ($request) {
                return [
                    'uuid' => $request->uuid,
                    'token' => $request->token,
                    'status' => $request->status,
                    'todos' => $request->todos,
                    'rejection_reason' => $request->rejection_reason,
                    'rejected_by_email' => $request->rejected_by_email,
                    'approved_by_email' => $request->approved_by_email,
                    'created_at' => $request->created_at,
                    'approved_at' => $request->approved_at,
                    'rejected_at' => $request->rejected_at,
                    'public_url' => $this->closureRequestService->getPublicUrl($request->token),
                ];
            })->toArray();

            return ApiResponse::success([
                'closure_requests' => $closureRequestsData,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch closure requests for guest', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch closure requests: '.$e->getMessage(), 500);
        }
    }

    /** Return file for API with view-only url (never original). */
    private function fileViewUrlOrNull(?object $file): ?array
    {
        if (! $file) {
            return null;
        }
        $v = $file->metadata['variants'] ?? null;
        $url = null;
        if (is_array($v)) {
            $url = $v['medium'] ?? $v['thumb'] ?? null;
        }

        return [
            'url' => $url,
            'type' => $file->type,
            'filename' => $file->filename ?? null,
        ];
    }
}
