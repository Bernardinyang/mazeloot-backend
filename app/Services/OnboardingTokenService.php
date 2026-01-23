<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\UserOnboardingToken;

class OnboardingTokenService
{
    /**
     * Generate a token for product onboarding.
     */
    public function generate(User $user, Product $product, int $expirationHours = 24): UserOnboardingToken
    {
        // Check if token already exists and is valid
        $existingToken = UserOnboardingToken::where('user_uuid', $user->uuid)
            ->where('product_uuid', $product->uuid)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();

        if ($existingToken) {
            return $existingToken;
        }

        // Create new token
        return UserOnboardingToken::create([
            'user_uuid' => $user->uuid,
            'product_uuid' => $product->uuid,
            'token' => UserOnboardingToken::generateToken(),
            'expires_at' => now()->addHours($expirationHours),
        ]);
    }

    /**
     * Verify token validity.
     */
    public function verify(string $token): ?UserOnboardingToken
    {
        $tokenModel = UserOnboardingToken::where('token', $token)->first();

        if (! $tokenModel || ! $tokenModel->isValid()) {
            return null;
        }

        return $tokenModel;
    }

    /**
     * Check if token is valid.
     */
    public function isValid(UserOnboardingToken $token): bool
    {
        return $token->isValid();
    }
}
