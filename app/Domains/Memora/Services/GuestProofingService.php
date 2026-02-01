<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraGuestProofingToken;
use Carbon\Carbon;

class GuestProofingService
{
    /**
     * Generate a guest token for a proofing
     */
    public function generateToken(string $proofingId, string $email): MemoraGuestProofingToken
    {
        $proofing = MemoraProofing::findOrFail($proofingId);

        // Allow token generation for active or completed proofing (view-only for completed)
        if (! in_array($proofing->status->value, ['active', 'completed'])) {
            throw new \RuntimeException('Proofing is not accessible. Only active or completed proofing can be accessed publicly.');
        }

        // Check if email is in the allowed emails list (if list exists)
        $allowedEmails = $proofing->allowed_emails ?? [];
        if (! empty($allowedEmails) && ! in_array(strtolower($email), array_map('strtolower', $allowedEmails))) {
            throw new \RuntimeException('This email is not authorized to access this proofing.');
        }

        // Create token that expires in 7 days
        return MemoraGuestProofingToken::create([
            'proofing_uuid' => $proofingId,
            'email' => $email,
            'expires_at' => Carbon::now()->addDays(7),
        ]);
    }

    /**
     * Get proofing by guest token
     */
    public function getProofingByToken(string $token): MemoraProofing
    {
        $guestToken = MemoraGuestProofingToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return $guestToken->proofing;
    }

    /**
     * Mark token as used (when proofing is completed)
     */
    public function markTokenAsUsed(string $token): void
    {
        $guestToken = MemoraGuestProofingToken::where('token', $token)->first();
        if ($guestToken) {
            $guestToken->markAsUsed();
        }
    }
}
