<?php

namespace App\Http\Controllers\V1;

use App\Enums\EarlyAccessRequestStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\EarlyAccessRequest;
use App\Services\EarlyAccess\EarlyAccessRequestService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarlyAccessController extends Controller
{
    public function __construct(
        protected EarlyAccessRequestService $requestService
    ) {}

    /**
     * Request early access for authenticated user.
     */
    public function requestEarlyAccess(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return ApiResponse::errorUnauthorized();
        }

        // Check if user already has early access
        if ($user->hasEarlyAccess()) {
            return ApiResponse::error('You already have early access', 'ALREADY_HAS_ACCESS', 400);
        }

        // Check if user already has a pending request
        $pendingRequest = EarlyAccessRequest::where('user_uuid', $user->uuid)
            ->where('status', EarlyAccessRequestStatusEnum::PENDING)
            ->first();

        if ($pendingRequest) {
            return ApiResponse::error('You already have a pending early access request', 'PENDING_REQUEST_EXISTS', 400);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        // Create request record using service
        $requestRecord = $this->requestService->createRequest(
            $user->uuid,
            $validated['reason'] ?? null
        );

        return ApiResponse::successCreated([
            'message' => 'Early access request submitted successfully. You will be notified when approved.',
            'request_uuid' => $requestRecord->uuid,
        ]);
    }

    /**
     * Get user's early access request status.
     */
    public function getRequestStatus(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return ApiResponse::errorUnauthorized();
        }

        $requestRecord = EarlyAccessRequest::where('user_uuid', $user->uuid)
            ->orderByDesc('created_at')
            ->first();

        if (!$requestRecord) {
            return ApiResponse::successOk([
                'status' => null,
                'message' => 'No early access request found',
            ]);
        }

        return ApiResponse::successOk([
            'uuid' => $requestRecord->uuid,
            'status' => $requestRecord->status->value,
            'reason' => $requestRecord->reason,
            'rejection_reason' => $requestRecord->rejection_reason,
            'created_at' => $requestRecord->created_at->toIso8601String(),
            'reviewed_at' => $requestRecord->reviewed_at?->toIso8601String(),
        ]);
    }

    /**
     * Check if user has access to a specific feature.
     */
    public function checkFeature(Request $request, string $feature): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return ApiResponse::errorUnauthorized();
        }

        $hasAccess = $user->hasEarlyAccess() && $user->earlyAccess->hasFeature($feature);

        return ApiResponse::successOk([
            'feature' => $feature,
            'has_access' => $hasAccess,
            'release_version' => $user->earlyAccess?->release_version,
        ]);
    }

    /**
     * Get user's available features.
     */
    public function getAvailableFeatures(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user || !$user->hasEarlyAccess()) {
            return ApiResponse::successOk([
                'features' => [],
                'release_version' => null,
            ]);
        }

        return ApiResponse::successOk([
            'features' => $user->earlyAccess->feature_flags ?? [],
            'release_version' => $user->earlyAccess->release_version,
        ]);
    }
}
