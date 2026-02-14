<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private ReferralService $referralService
    ) {}

    /**
     * Get referral link and stats for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::errorUnauthorized('User not authenticated.');
        }

        $code = $this->referralService->getOrCreateCode($user);
        $link = $this->referralService->getReferralLink($user);
        $stats = $this->referralService->getStats($user);

        return ApiResponse::successOk([
            'referral_code' => $code,
            'referral_link' => $link,
            'total_registered' => $stats['total_registered'],
            'total_conversions' => $stats['total_conversions'],
            'total_earned_cents' => $stats['total_earned_cents'],
            'credit_balance_cents' => $stats['credit_balance_cents'],
        ]);
    }

    /**
     * Send a referral invite by email.
     */
    public function sendInvite(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::errorUnauthorized('User not authenticated.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->referralService->sendInvite($user, $validated['email']);

        return ApiResponse::successOk([
            'message' => 'Invite sent.',
        ]);
    }

    /**
     * List all referrals for the authenticated user.
     */
    public function referrals(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::errorUnauthorized('User not authenticated.');
        }

        $referrals = $this->referralService->listReferrals($user);

        return ApiResponse::successOk(['referrals' => $referrals]);
    }
}
