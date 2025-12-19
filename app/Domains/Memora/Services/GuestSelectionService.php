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

