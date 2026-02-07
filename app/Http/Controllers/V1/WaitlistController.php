<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\WaitlistRequest;
use App\Jobs\NotifyAdminsWaitlist;
use App\Models\Product;
use App\Models\Waitlist;
use App\Notifications\WaitlistConfirmationNotification;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\Notifiable;

class WaitlistController extends Controller
{
    /**
     * Join the waitlist.
     */
    public function store(WaitlistRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Auto-assign Memora product UUID if not provided (since it's the first product launching)
        $productUuid = $validated['product_uuid'] ?? null;
        $productName = 'Memora';
        if (! $productUuid) {
            $memoraProduct = Product::where('slug', 'memora')->where('is_active', true)->first();
            if ($memoraProduct) {
                $productUuid = $memoraProduct->uuid;
                $productName = $memoraProduct->name;
            }
        } else {
            $product = Product::where('uuid', $productUuid)->first();
            if ($product) {
                $productName = $product->name;
            }
        }

        // Check if email already exists for this product
        $existing = Waitlist::where('email', $validated['email'])
            ->where('product_uuid', $productUuid)
            ->first();

        if ($existing) {
            return ApiResponse::success([
                'message' => 'You\'re already on the waitlist! We\'ll notify you when we launch.',
            ], 200);
        }

        $waitlist = Waitlist::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'product_uuid' => $productUuid,
            'status' => 'not_registered',
        ]);

        // Notify admins
        try {
            NotifyAdminsWaitlist::dispatchSync(
                $validated['name'],
                $validated['email'],
                $waitlist->uuid,
                $productUuid
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to notify admins about waitlist signup', [
                'waitlist_uuid' => $waitlist->uuid,
                'error' => $e->getMessage(),
            ]);
        }

        // Send confirmation email to user
        try {
            $notifiable = new class($validated['email'])
            {
                use Notifiable;

                public function __construct(public string $email) {}
            };

            $notifiable->notify(
                new WaitlistConfirmationNotification($validated['name'], $productName)
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send waitlist confirmation email', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success([
            'message' => 'Thanks for joining! We\'ll notify you when we launch.',
        ], 201);
    }
}
