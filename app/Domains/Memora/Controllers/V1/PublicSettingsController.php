<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraSettings;
use App\Domains\Memora\Resources\V1\PublicSettingsResource;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSettingsController extends Controller
{
    /**
     * Get public settings (no authentication required)
     * Returns only public branding information for display in public collections
     *
     * Optional query parameters:
     * - collectionId: Get settings for the collection owner
     * - userId: Get settings for a specific user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = null;

            // If collectionId is provided, get the user from the collection
            if ($request->has('collectionId')) {
                $collection = MemoraCollection::where('uuid', $request->query('collectionId'))
                    ->select('user_uuid')
                    ->first();

                if ($collection) {
                    $userId = $collection->user_uuid;
                }
            } elseif ($request->has('userId')) {
                $userId = $request->query('userId');
            }

            // Get settings for the user (or first available if no user specified)
            if ($userId) {
                $settings = MemoraSettings::where('user_uuid', $userId)->first();
            } else {
                // Fallback to first settings if no user specified
                $settings = MemoraSettings::first();
            }

            if (! $settings) {
                // Return empty settings if none exist
                return ApiResponse::success([
                    'branding' => [
                        'logoUrl' => null,
                        'faviconUrl' => null,
                        'name' => null,
                        'website' => null,
                        'location' => null,
                        'tagline' => null,
                        'description' => null,
                    ],
                ]);
            }

            // Load logo and favicon relationships
            $settings->load(['logo', 'favicon']);

            return ApiResponse::success(new PublicSettingsResource($settings));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch public settings', [
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch settings', 'FETCH_FAILED', 500);
        }
    }
}
