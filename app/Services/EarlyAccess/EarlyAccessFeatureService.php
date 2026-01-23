<?php

namespace App\Services\EarlyAccess;

use App\Models\EarlyAccessUser;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;

class EarlyAccessFeatureService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Grant a feature flag to a user.
     *
     * @param  string  $userUuid
     * @param  string  $feature
     * @return bool
     */
    public function grantFeature(string $userUuid, string $feature): bool
    {
        $earlyAccess = EarlyAccessUser::where('user_uuid', $userUuid)
            ->where('is_active', true)
            ->first();

        if (!$earlyAccess) {
            return false;
        }

        $flags = $earlyAccess->feature_flags ?? [];
        if (!in_array($feature, $flags)) {
            $flags[] = $feature;
            $earlyAccess->update(['feature_flags' => $flags]);

            // Send notification
            $this->notificationService->create(
                $userUuid,
                'system',
                'early_access_feature_available',
                'New Feature Available',
                "You now have access to: {$feature}",
                'Check it out in your dashboard!',
                '/dashboard',
                ['feature' => $feature]
            );
        }

        return true;
    }

    /**
     * Revoke a feature flag from a user.
     *
     * @param  string  $userUuid
     * @param  string  $feature
     * @return bool
     */
    public function revokeFeature(string $userUuid, string $feature): bool
    {
        $earlyAccess = EarlyAccessUser::where('user_uuid', $userUuid)->first();

        if (!$earlyAccess) {
            return false;
        }

        $flags = array_filter(
            $earlyAccess->feature_flags ?? [],
            fn($f) => $f !== $feature
        );

        $earlyAccess->update(['feature_flags' => array_values($flags)]);

        return true;
    }

    /**
     * Grant a feature to all early access users.
     *
     * @param  string  $feature
     * @return int  Number of users updated
     */
    public function grantToAll(string $feature): int
    {
        return DB::transaction(function () use ($feature) {
            $updated = 0;

            EarlyAccessUser::where('is_active', true)
                ->get()
                ->each(function ($earlyAccess) use ($feature, &$updated) {
                    $flags = $earlyAccess->feature_flags ?? [];
                    if (!in_array($feature, $flags)) {
                        $flags[] = $feature;
                        $earlyAccess->update(['feature_flags' => $flags]);

                        // Send notification
                        $this->notificationService->create(
                            $earlyAccess->user_uuid,
                            'system',
                            'early_access_feature_available',
                            'New Feature Available',
                            "You now have access to: {$feature}",
                            'Check it out in your dashboard!',
                            '/dashboard',
                            ['feature' => $feature]
                        );

                        $updated++;
                    }
                });

            return $updated;
        });
    }

    /**
     * Rollout feature to a percentage of early access users.
     *
     * @param  string  $feature
     * @param  int  $percentage  Percentage (0-100)
     * @return int  Number of users updated
     */
    public function rolloutPercentage(string $feature, int $percentage): int
    {
        $total = EarlyAccessUser::where('is_active', true)->count();
        $target = (int) ceil($total * ($percentage / 100));

        return DB::transaction(function () use ($feature, $target) {
            $updated = 0;

            EarlyAccessUser::where('is_active', true)
                ->inRandomOrder()
                ->limit($target)
                ->get()
                ->each(function ($earlyAccess) use ($feature, &$updated) {
                    $flags = $earlyAccess->feature_flags ?? [];
                    if (!in_array($feature, $flags)) {
                        $flags[] = $feature;
                        $earlyAccess->update(['feature_flags' => $flags]);

                        // Send notification
                        $this->notificationService->create(
                            $earlyAccess->user_uuid,
                            'system',
                            'early_access_feature_available',
                            'New Feature Available',
                            "You now have access to: {$feature}",
                            'Check it out in your dashboard!',
                            '/dashboard',
                            ['feature' => $feature]
                        );

                        $updated++;
                    }
                });

            return $updated;
        });
    }

    /**
     * Update release version for all early access users.
     *
     * @param  string  $version
     * @return int  Number of users updated
     */
    public function updateReleaseVersion(string $version): int
    {
        return EarlyAccessUser::where('is_active', true)
            ->update(['release_version' => $version]);
    }
}
