<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmailVerificationService
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
     * Generate a random verification code.
     */
    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Send verification code to user.
     */
    public function sendVerificationCode(User $user): EmailVerificationCode
    {
        // Invalidate all existing unverified codes for this user
        EmailVerificationCode::where('user_uuid', $user->uuid)
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);

        // Create new verification code
        $code = EmailVerificationCode::create([
            'user_uuid' => $user->uuid,
            'code' => $this->generateCode(),
            'expires_at' => Carbon::now()->addMinutes(self::CODE_EXPIRATION_MINUTES),
        ]);

        // Send notification
        $user->notify(new EmailVerificationNotification($code->code));

        // Log activity for verification email notification
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                'notification_sent',
                $user,
                'Email verification code sent',
                [
                    'channel' => 'email',
                    'notification' => 'EmailVerificationNotification',
                    'code_uuid' => $code->uuid ?? null,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to log email verification notification activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $code;
    }

    /**
     * Verify the code for a user.
     */
    public function verifyCode(User $user, string $code): bool
    {
        $verificationCode = EmailVerificationCode::where('user_uuid', $user->uuid)
            ->where('code', $code)
            ->whereNull('verified_at')
            ->first();

        if (! $verificationCode) {
            return false;
        }

        if ($verificationCode->isExpired()) {
            return false;
        }

        // Mark code as verified
        $verificationCode->markAsVerified();

        // Mark user email as verified
        $user->update(['email_verified_at' => now()]);

        return true;
    }

    /**
     * Check if user has a pending verification code.
     */
    public function hasPendingCode(User $user): bool
    {
        return EmailVerificationCode::where('user_uuid', $user->uuid)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
