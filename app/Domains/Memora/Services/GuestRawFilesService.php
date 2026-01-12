<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraRawFiles;
use App\Models\GuestRawFilesToken;
use Carbon\Carbon;

class GuestRawFilesService
{
    /**
     * Generate a guest token for raw files
     */
    public function generateToken(string $rawFilesId, string $email): GuestRawFilesToken
    {
        $rawFiles = MemoraRawFiles::findOrFail($rawFilesId);

        // Allow token generation for active raw files
        if ($rawFiles->status?->value !== 'active' && $rawFiles->status !== 'active') {
            throw new \RuntimeException('Raw Files phase is not accessible. Only active raw files phases can be accessed publicly.');
        }

        // Check if email is in the allowed emails list (if list exists)
        $allowedEmails = $rawFiles->allowed_emails ?? [];
        if (! empty($allowedEmails) && ! in_array(strtolower($email), array_map('strtolower', $allowedEmails))) {
            throw new \RuntimeException('This email is not authorized to access this raw files phase.');
        }

        // Create token that expires in 7 days
        return GuestRawFilesToken::create([
            'raw_files_uuid' => $rawFilesId,
            'email' => $email,
            'expires_at' => Carbon::now()->addDays(7),
        ]);
    }

    /**
     * Get raw files by guest token
     */
    public function getRawFilesByToken(string $token): MemoraRawFiles
    {
        $guestToken = GuestRawFilesToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return $guestToken->rawFiles;
    }

    /**
     * Mark token as used
     */
    public function markTokenAsUsed(string $token): void
    {
        $guestToken = GuestRawFilesToken::where('token', $token)->first();
        if ($guestToken) {
            $guestToken->markAsUsed();
        }
    }
}
