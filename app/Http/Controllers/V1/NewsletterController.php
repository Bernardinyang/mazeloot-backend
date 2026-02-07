<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\NewsletterRequest;
use App\Jobs\NotifyAdminsNewsletter;
use App\Models\Newsletter;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class NewsletterController extends Controller
{
    /**
     * Subscribe to newsletter.
     */
    public function store(NewsletterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim($validated['email']));

        // Check if email already exists
        $existing = Newsletter::where('email', $email)->first();

        if ($existing) {
            if ($existing->is_active) {
                return ApiResponse::success([
                    'message' => 'You\'re already subscribed to our newsletter!',
                ], 200);
            }

            // Reactivate if previously unsubscribed
            $existing->update([
                'is_active' => true,
                'unsubscribed_at' => null,
            ]);

            return ApiResponse::success([
                'message' => 'Thanks for resubscribing! We\'ll keep you updated.',
            ], 200);
        }

        $newsletter = Newsletter::create([
            'email' => $email,
            'is_active' => true,
        ]);

        // Notify admins
        try {
            NotifyAdminsNewsletter::dispatchSync(
                $email,
                $newsletter->uuid
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to notify admins about newsletter subscription', [
                'newsletter_uuid' => $newsletter->uuid,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::success([
            'message' => 'Thanks for subscribing! We\'ll keep you updated.',
        ], 201);
    }

    /**
     * Get newsletter info by token (for display before unsubscribe).
     */
    public function getByToken(string $token): JsonResponse
    {
        try {
            $decoded = base64_decode($token, true);
            if ($decoded === false) {
                return ApiResponse::error('Invalid unsubscribe link.', 400);
            }

            $data = json_decode($decoded, true);
            if (!isset($data['email']) || !isset($data['uuid'])) {
                return ApiResponse::error('Invalid unsubscribe link.', 400);
            }

            $newsletter = Newsletter::where('uuid', $data['uuid'])
                ->where('email', $data['email'])
                ->first();

            if (!$newsletter) {
                return ApiResponse::error('Newsletter subscription not found.', 404);
            }

            return ApiResponse::success([
                'email' => $newsletter->email,
                'uuid' => $newsletter->uuid,
                'is_active' => $newsletter->is_active,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to get newsletter by token', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to load unsubscribe page.', 500);
        }
    }

    /**
     * Unsubscribe from newsletter.
     */
    public function unsubscribe(string $token): JsonResponse
    {
        try {
            $decoded = base64_decode($token, true);
            if ($decoded === false) {
                return ApiResponse::error('Invalid unsubscribe link.', 400);
            }

            $data = json_decode($decoded, true);
            if (!isset($data['email']) || !isset($data['uuid'])) {
                return ApiResponse::error('Invalid unsubscribe link.', 400);
            }

            $newsletter = Newsletter::where('uuid', $data['uuid'])
                ->where('email', $data['email'])
                ->first();

            if (!$newsletter) {
                return ApiResponse::error('Newsletter subscription not found.', 404);
            }

            if (!$newsletter->is_active) {
                return ApiResponse::success([
                    'message' => 'You\'re already unsubscribed from our newsletter.',
                    'email' => $newsletter->email,
                ], 200);
            }

            $newsletter->update([
                'is_active' => false,
                'unsubscribed_at' => now(),
            ]);

            return ApiResponse::success([
                'message' => 'You\'ve been successfully unsubscribed from our newsletter.',
                'email' => $newsletter->email,
            ], 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to unsubscribe from newsletter', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to unsubscribe. Please try again.', 500);
        }
    }

    /**
     * Generate unsubscribe token.
     */
    public static function generateUnsubscribeToken(string $email, string $uuid): string
    {
        $data = json_encode([
            'email' => $email,
            'uuid' => $uuid,
        ]);

        return base64_encode($data);
    }
}
