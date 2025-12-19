<?php

namespace App\Services\Auth;

use App\Models\MagicLinkToken;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MagicLinkService
{
    /**
     * Token expiration time in minutes.
     */
    const TOKEN_EXPIRATION_MINUTES = 15;

    /**
     * Generate a random token.
     */
    protected function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Send magic link to user.
     */
    public function sendMagicLink(User $user): MagicLinkToken
    {
        // Invalidate all existing unused tokens for this user
        MagicLinkToken::where('user_uuid', $user->uuid)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        // Create new magic link token
        $token = MagicLinkToken::create([
            'user_uuid' => $user->uuid,
            'token' => $this->generateToken(),
            'expires_at' => Carbon::now()->addMinutes(self::TOKEN_EXPIRATION_MINUTES),
        ]);

        // Send notification with magic link
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        $magicLink = $frontendUrl . '/auth/magic-link/verify?token=' . $token->token . '&email=' . urlencode($user->email);
        
        $user->notify(new MagicLinkNotification($magicLink));

        return $token;
    }

    /**
     * Verify the magic link token.
     */
    public function verifyToken(string $token, string $email): ?MagicLinkToken
    {
        $magicLinkToken = MagicLinkToken::where('token', $token)
            ->whereNull('used_at')
            ->with('user')
            ->first();

        if (!$magicLinkToken) {
            return null;
        }

        if ($magicLinkToken->isExpired()) {
            return null;
        }

        // Verify the token belongs to the email provided
        if ($magicLinkToken->user->email !== $email) {
            return null;
        }

        return $magicLinkToken;
    }
}

