<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProofingApprovalRequest;
use App\Domains\Memora\Services\ProofingApprovalRequestService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProofingApprovalRequestController extends Controller
{
    protected ProofingApprovalRequestService $approvalRequestService;

    public function __construct(ProofingApprovalRequestService $approvalRequestService)
    {
        $this->approvalRequestService = $approvalRequestService;
    }

    /**
     * Create an approval request (authenticated - creative only)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'proofing_id' => ['required', 'string'],
            'media_id' => ['required', 'string'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $approvalRequest = $this->approvalRequestService->create(
                proofingId: $request->input('proofing_id'),
                mediaId: $request->input('media_id'),
                message: $request->input('message'),
                userId: Auth::user()->uuid
            );

            return ApiResponse::success([
                'approval_request' => [
                    'uuid' => $approvalRequest->uuid,
                    'token' => $approvalRequest->token,
                    'url' => $this->approvalRequestService->getPublicUrl($approvalRequest->token),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create approval request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to create approval request: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get approval request by token (public - no auth required)
     */
    public function showByToken(string $token): JsonResponse
    {
        try {
            $approvalRequest = $this->approvalRequestService->findByToken($token);

            if (! $approvalRequest) {
                return ApiResponse::error('Approval request not found', 404);
            }

            $approvalRequest->load('user');

            return ApiResponse::success([
                'approval_request' => [
                    'uuid' => $approvalRequest->uuid,
                    'token' => $approvalRequest->token,
                    'status' => $approvalRequest->status,
                    'message' => $approvalRequest->message,
                    'proofing' => [
                        'uuid' => $approvalRequest->proofing->uuid,
                        'name' => $approvalRequest->proofing->name,
                        'primary_email' => $approvalRequest->proofing->primary_email,
                        'allowed_emails' => $approvalRequest->proofing->allowed_emails,
                        'has_password' => ! empty($approvalRequest->proofing->password),
                        'project_uuid' => $approvalRequest->proofing->project_uuid,
                        'max_revisions' => $approvalRequest->proofing->max_revisions,
                    ],
                    'creative_email' => $approvalRequest->user->email ?? null,
                    'media' => [
                        'uuid' => $approvalRequest->media->uuid,
                        'file' => $this->fileViewUrlOrNull($approvalRequest->media->file),
                    ],
                    'created_at' => $approvalRequest->created_at,
                    'rejection_reason' => $approvalRequest->rejection_reason,
                    'rejected_by_email' => $approvalRequest->rejected_by_email,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch approval request', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch approval request', 500);
        }
    }

    /**
     * Approve approval request (public - no auth required, but requires email verification)
     */
    public function approve(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $approvalRequest = $this->approvalRequestService->approve(
                token: $token,
                email: $request->input('email')
            );

            return ApiResponse::success([
                'approval_request' => [
                    'uuid' => $approvalRequest->uuid,
                    'status' => $approvalRequest->status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to approve approval request', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to approve approval request: '.$e->getMessage(), 500);
        }
    }

    /**
     * Reject approval request (public - no auth required, but requires email verification)
     */
    public function reject(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $approvalRequest = $this->approvalRequestService->reject(
                token: $token,
                email: $request->input('email'),
                reason: $request->input('reason')
            );

            return ApiResponse::success([
                'approval_request' => [
                    'uuid' => $approvalRequest->uuid,
                    'status' => $approvalRequest->status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reject approval request', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to reject approval request: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get approval requests for a media item (authenticated - creative only)
     */
    public function getByMedia(string $mediaId): JsonResponse
    {
        try {
            $approvalRequests = $this->approvalRequestService->getByMedia($mediaId, Auth::id());

            return ApiResponse::success([
                'approval_requests' => $approvalRequests,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch approval requests', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch approval requests: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get approval requests for a media item (public - guest token required)
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

            $approvalRequests = MemoraProofingApprovalRequest::where('media_uuid', $mediaId)
                ->orderBy('created_at', 'desc')
                ->get();

            $approvalRequestsData = $approvalRequests->map(function ($request) {
                return [
                    'uuid' => $request->uuid,
                    'token' => $request->token,
                    'status' => $request->status,
                    'message' => $request->message,
                    'rejection_reason' => $request->rejection_reason,
                    'rejected_by_email' => $request->rejected_by_email,
                    'approved_by_email' => $request->approved_by_email,
                    'created_at' => $request->created_at,
                    'approved_at' => $request->approved_at,
                    'rejected_at' => $request->rejected_at,
                    'public_url' => $this->approvalRequestService->getPublicUrl($request->token),
                ];
            })->toArray();

            return ApiResponse::success([
                'approval_requests' => $approvalRequestsData,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch approval requests for guest', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch approval requests: '.$e->getMessage(), 500);
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
        ];
    }
}
