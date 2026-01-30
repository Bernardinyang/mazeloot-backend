<?php

namespace App\Services\EarlyAccess;

use App\Models\EarlyAccessUser;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EarlyAccessService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Grant early access to a user with all reward types.
     *
     * @param  array  $rewards  Reward configuration
     */
    public function grantEarlyAccess(string $userUuid, array $rewards, ?Carbon $expiresAt = null): EarlyAccessUser
    {
        return DB::transaction(function () use ($userUuid, $rewards, $expiresAt) {
            $earlyAccess = EarlyAccessUser::updateOrCreate(
                ['user_uuid' => $userUuid],
                [
                    'discount_percentage' => $rewards['discount_percentage'] ?? 0,
                    'discount_rules' => $rewards['discount_rules'] ?? null,
                    'feature_flags' => $rewards['feature_flags'] ?? [],
                    'storage_multiplier' => $rewards['storage_multiplier'] ?? 1.0,
                    'priority_support' => $rewards['priority_support'] ?? false,
                    'exclusive_badge' => $rewards['exclusive_badge'] ?? true,
                    'trial_extension_days' => $rewards['trial_extension_days'] ?? 0,
                    'custom_branding_enabled' => $rewards['custom_branding_enabled'] ?? false,
                    'release_version' => $rewards['release_version'] ?? null,
                    'granted_at' => now(),
                    'expires_at' => $expiresAt,
                    'is_active' => true,
                    'notes' => $rewards['notes'] ?? null,
                ]
            );

            // Send notification
            $user = User::find($userUuid);
            if ($user) {
                $this->notificationService->create(
                    $userUuid,
                    'system',
                    'early_access_granted',
                    'Early Access Granted',
                    'You have been granted early access to Mazeloot!',
                    'You now have access to exclusive features, discounts, and priority support.',
                    null,
                    '/dashboard',
                    ['early_access' => true]
                );
            }

            return $earlyAccess;
        });
    }

    /**
     * Revoke early access from a user.
     */
    public function revokeEarlyAccess(string $userUuid): bool
    {
        return DB::transaction(function () use ($userUuid) {
            $earlyAccess = EarlyAccessUser::where('user_uuid', $userUuid)->first();

            if ($earlyAccess) {
                $earlyAccess->update(['is_active' => false]);

                // Send notification
                $this->notificationService->create(
                    $userUuid,
                    'system',
                    'early_access_revoked',
                    'Early Access Revoked',
                    'Your early access has been revoked.',
                    'You no longer have access to early access features.',
                    null,
                    '/dashboard',
                    ['early_access' => false]
                );

                return true;
            }

            return false;
        });
    }

    /**
     * Get active early access for a user.
     */
    public function getActiveEarlyAccess(string $userUuid): ?EarlyAccessUser
    {
        return EarlyAccessUser::where('user_uuid', $userUuid)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Get discount percentage for a product.
     */
    public function getDiscountForProduct(string $userUuid, string $productId): int
    {
        $earlyAccess = $this->getActiveEarlyAccess($userUuid);

        if (! $earlyAccess) {
            return 0;
        }

        return $earlyAccess->getDiscountForProduct($productId);
    }

    /**
     * Update reward configuration for a user.
     */
    public function updateRewards(string $userUuid, array $rewards): ?EarlyAccessUser
    {
        $earlyAccess = EarlyAccessUser::where('user_uuid', $userUuid)->first();

        if (! $earlyAccess) {
            return null;
        }

        $updateData = [];

        if (isset($rewards['discount_percentage'])) {
            $updateData['discount_percentage'] = $rewards['discount_percentage'];
        }

        if (isset($rewards['discount_rules'])) {
            $updateData['discount_rules'] = $rewards['discount_rules'];
        }

        if (isset($rewards['feature_flags'])) {
            $updateData['feature_flags'] = $rewards['feature_flags'];
        }

        if (isset($rewards['storage_multiplier'])) {
            $updateData['storage_multiplier'] = $rewards['storage_multiplier'];
        }

        if (isset($rewards['priority_support'])) {
            $updateData['priority_support'] = $rewards['priority_support'];
        }

        if (isset($rewards['exclusive_badge'])) {
            $updateData['exclusive_badge'] = $rewards['exclusive_badge'];
        }

        if (isset($rewards['trial_extension_days'])) {
            $updateData['trial_extension_days'] = $rewards['trial_extension_days'];
        }

        if (isset($rewards['custom_branding_enabled'])) {
            $updateData['custom_branding_enabled'] = $rewards['custom_branding_enabled'];
        }

        if (isset($rewards['release_version'])) {
            $updateData['release_version'] = $rewards['release_version'];
        }

        if (isset($rewards['expires_at'])) {
            $updateData['expires_at'] = $rewards['expires_at'] ? Carbon::parse($rewards['expires_at']) : null;
        }

        if (isset($rewards['notes'])) {
            $updateData['notes'] = $rewards['notes'];
        }

        $earlyAccess->update($updateData);

        return $earlyAccess->fresh();
    }
}
