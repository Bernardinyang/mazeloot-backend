<?php

namespace App\Services\Auth;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Carbon\Carbon;

class PasswordResetService
{
    /**
     * Code expiration time in minutes.
     */
    const CODE_EXPIRATION_MINUTES = 15;

    /**
     * Code length.
     */
    const CODE_LENGTH = 6;

    /**
     * Generate a random reset code.
     */
    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Send password reset code to user.
     */
    public function sendResetCode(User $user): PasswordResetToken
    {
        // Invalidate all existing unused codes for this user
        PasswordResetToken::where('user_uuid', $user->uuid)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        // Create new reset code
        $token = PasswordResetToken::create([
            'user_uuid' => $user->uuid,
            'code' => $this->generateCode(),
            'expires_at' => Carbon::now()->addMinutes(self::CODE_EXPIRATION_MINUTES),
        ]);

        // Send notification
        $user->notify(new PasswordResetNotification($token->code));

        return $token;
    }

    /**
     * Verify the reset code for a user.
     */
    public function verifyCode(User $user, string $code): ?PasswordResetToken
    {
        $resetToken = PasswordResetToken::where('user_uuid', $user->uuid)
            ->where('code', $code)
            ->whereNull('used_at')
            ->first();

        if (!$resetToken) {
            return null;
        }

        if ($resetToken->isExpired()) {
            return null;
        }

        return $resetToken;
    }

    /**
     * Reset password using verified code.
     */
    public function resetPassword(User $user, string $code, string $newPassword): bool
    {
        $resetToken = $this->verifyCode($user, $code);

        if (!$resetToken) {
            return false;
        }

        // Update password
        $user->update(['password' => bcrypt($newPassword)]);

        // Mark token as used
        $resetToken->markAsUsed();

        return true;
    }
}

