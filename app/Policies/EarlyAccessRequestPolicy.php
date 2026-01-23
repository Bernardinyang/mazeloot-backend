<?php

namespace App\Policies;

use App\Enums\EarlyAccessRequestStatusEnum;
use App\Models\EarlyAccessRequest;
use App\Models\User;

class EarlyAccessRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EarlyAccessRequest $earlyAccessRequest): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can approve the request.
     */
    public function approve(User $user, EarlyAccessRequest $earlyAccessRequest): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        // Can only approve pending requests
        return $earlyAccessRequest->status === EarlyAccessRequestStatusEnum::PENDING;
    }

    /**
     * Determine whether the user can reject the request.
     */
    public function reject(User $user, EarlyAccessRequest $earlyAccessRequest): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        // Can only reject pending requests
        return $earlyAccessRequest->status === EarlyAccessRequestStatusEnum::PENDING;
    }
}
