<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraSelection;
use App\Models\GuestSelectionToken;
use Carbon\Carbon;

class GuestSelectionService
{
    /**
     * Generate a guest token for a selection
     */
    public function generateToken(string $selectionId, string $email): GuestSelectionToken
    {
        $selection = MemoraSelection::findOrFail($selectionId);

        // Allow token generation for active or completed selections (view-only for completed)
        if (! in_array($selection->status->value, ['active', 'completed'])) {
            throw new \RuntimeException('Selection is not accessible. Only active or completed selections can be accessed publicly.');
        }

        // Check if email is in the allowed emails list (if list exists)
        $allowedEmails = $selection->allowed_emails ?? [];
        if (! empty($allowedEmails) && ! in_array(strtolower($email), array_map('strtolower', $allowedEmails))) {
            throw new \RuntimeException('This email is not authorized to access this selection.');
        }

        // Create token that expires in 7 days
        return GuestSelectionToken::create([
            'selection_uuid' => $selectionId,
            'email' => $email,
            'expires_at' => Carbon::now()->addDays(7),
        ]);
    }

    /**
     * Get selection by guest token
     */
    public function getSelectionByToken(string $token): MemoraSelection
    {
        $guestToken = GuestSelectionToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return $guestToken->selection;
    }

    /**
     * Mark token as used (when selection is completed)
     */
    public function markTokenAsUsed(string $token): void
    {
        $guestToken = GuestSelectionToken::where('token', $token)->first();
        if ($guestToken) {
            $guestToken->markAsUsed();
        }
    }
}
