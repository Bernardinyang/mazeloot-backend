<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraGuestRawFileToken;
use App\Domains\Memora\Models\MemoraRawFile;
use Carbon\Carbon;

class GuestRawFileService
{
    /**
     * Generate a guest token for a raw file
     */
    public function generateToken(string $rawFileId, string $email): MemoraGuestRawFileToken
    {
        $rawFile = MemoraRawFile::findOrFail($rawFileId);

        // Allow token generation for active or completed raw files (view-only for completed)
        if (! in_array($rawFile->status->value, ['active', 'completed'])) {
            throw new \RuntimeException('Raw file is not accessible. Only active or completed raw files can be accessed publicly.');
        }

        // Check if email is in the allowed emails list (if list exists)
        $allowedEmails = $rawFile->allowed_emails ?? [];
        if (! empty($allowedEmails) && ! in_array(strtolower($email), array_map('strtolower', $allowedEmails))) {
            throw new \RuntimeException('This email is not authorized to access this raw file.');
        }

        // Create token that expires in 7 days
        return MemoraGuestRawFileToken::create([
            'raw_file_uuid' => $rawFileId,
            'email' => $email,
            'expires_at' => Carbon::now()->addDays(7),
        ]);
    }

    /**
     * Get raw file by guest token
     */
    public function getRawFileByToken(string $token): MemoraRawFile
    {
        $guestToken = MemoraGuestRawFileToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return $guestToken->rawFile;
    }

    /**
     * Mark token as used (when raw file is completed)
     */
    public function markTokenAsUsed(string $token): void
    {
        $guestToken = MemoraGuestRawFileToken::where('token', $token)->first();
        if ($guestToken) {
            $guestToken->markAsUsed();
        }
    }
}
