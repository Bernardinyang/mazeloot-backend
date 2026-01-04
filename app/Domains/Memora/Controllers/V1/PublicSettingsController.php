<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraSettings;
use App\Domains\Memora\Models\MemoraSocialLink;
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

    /**
     * Verify homepage password (no authentication required)
     */
    public function verifyHomepagePassword(Request $request): JsonResponse
    {
        try {
            $password = $request->input('password');
            $userId = $request->input('userId');

            if (! $userId) {
                return ApiResponse::error('User ID is required', 'VALIDATION_ERROR', 400);
            }

            $settings = MemoraSettings::where('user_uuid', $userId)->first();

            if (! $settings) {
                return ApiResponse::error('Settings not found', 'NOT_FOUND', 404);
            }

            if (! $settings->homepage_status) {
                return ApiResponse::error('Homepage is disabled', 'HOMEPAGE_DISABLED', 403);
            }

            $homepagePassword = $settings->homepage_password;

            if (! $homepagePassword) {
                return ApiResponse::success(['verified' => true]);
            }

            if ($password === $homepagePassword) {
                return ApiResponse::success(['verified' => true]);
            }

            return ApiResponse::error('Incorrect password', 'INVALID_PASSWORD', 401);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to verify homepage password', [
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to verify password', 'VERIFY_FAILED', 500);
        }
    }

    /**
     * Get homepage collections (no authentication required)
     */
    public function getHomepageCollections(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('userId');

            if (! $userId) {
                return ApiResponse::error('User ID is required', 'VALIDATION_ERROR', 400);
            }

            $settings = MemoraSettings::where('user_uuid', $userId)->first();

            if (! $settings) {
                return ApiResponse::error('Settings not found', 'NOT_FOUND', 404);
            }

            if (! $settings->homepage_status) {
                return ApiResponse::error('Homepage is disabled', 'HOMEPAGE_DISABLED', 403);
            }

            // Check if password is required
            $hasPassword = ! empty($settings->homepage_password);
            if ($hasPassword) {
                // Check if password was verified via request header
                $verified = $request->header('X-Homepage-Verified') === 'true';
                if (! $verified) {
                    return ApiResponse::error('Password required', 'PASSWORD_REQUIRED', 401);
                }
            }

            $collections = MemoraCollection::where('user_uuid', $userId)
                ->where('status', 'active')
                ->with(['preset'])
                ->get()
                ->filter(function ($collection) {
                    $settings = $collection->settings ?? [];
                    $showOnHomepage = $settings['privacy']['showOnHomepage'] ?? $settings['showOnHomepage'] ?? false;

                    return $showOnHomepage;
                })
                ->map(function ($collection) {
                    $settings = $collection->settings ?? [];

                    return [
                        'id' => $collection->uuid,
                        'uuid' => $collection->uuid,
                        'name' => $collection->name,
                        'title' => $collection->name,
                        'image' => $collection->image ?? $collection->thumbnail,
                        'thumbnail' => $collection->thumbnail ?? $collection->image,
                        'eventDate' => $settings['eventDate'] ?? null,
                        'description' => $collection->description,
                    ];
                })
                ->values();

            return ApiResponse::success($collections);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch homepage collections', [
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch collections', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Get public social links (no authentication required)
     */
    public function getSocialLinks(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('userId');

            if (! $userId) {
                return ApiResponse::error('User ID is required', 'VALIDATION_ERROR', 400);
            }

            $links = MemoraSocialLink::where('user_uuid', $userId)
                ->where('is_active', true)
                ->with('platform')
                ->orderBy('order')
                ->get()
                ->map(function ($link) {
                    return [
                        'id' => $link->uuid,
                        'uuid' => $link->uuid,
                        'url' => $link->url,
                        'isActive' => $link->is_active ?? true,
                        'platform' => $link->platform ? [
                            'id' => $link->platform->uuid,
                            'uuid' => $link->platform->uuid,
                            'slug' => $link->platform->slug,
                            'name' => $link->platform->name,
                        ] : null,
                    ];
                })
                ->values();

            return ApiResponse::success($links);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch social links', [
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch social links', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Get featured media for homepage (no authentication required)
     */
    public function getFeaturedMedia(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('userId');

            if (! $userId) {
                return ApiResponse::error('User ID is required', 'VALIDATION_ERROR', 400);
            }

            $settings = MemoraSettings::where('user_uuid', $userId)->first();

            if (! $settings) {
                return ApiResponse::error('Settings not found', 'NOT_FOUND', 404);
            }

            if (! $settings->homepage_status) {
                return ApiResponse::error('Homepage is disabled', 'HOMEPAGE_DISABLED', 403);
            }

            // Check if password is required
            $hasPassword = ! empty($settings->homepage_password);
            if ($hasPassword) {
                $verified = $request->header('X-Homepage-Verified') === 'true';
                if (! $verified) {
                    return ApiResponse::error('Password required', 'PASSWORD_REQUIRED', 401);
                }
            }

            $featuredMedia = MemoraMedia::where('user_uuid', $userId)
                ->where('is_featured', true)
                ->with(['file', 'mediaSet.collection'])
                ->orderBy('featured_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($media) {
                    $file = $media->file;
                    $collection = $media->mediaSet?->collection;

                    return [
                        'id' => $media->uuid,
                        'uuid' => $media->uuid,
                        'url' => $file?->url ?? null,
                        'thumbnail' => $file?->url ?? null,
                        'type' => $file?->type?->value ?? 'image',
                        'collectionId' => $collection?->uuid ?? null,
                        'collectionName' => $collection?->name ?? null,
                    ];
                })
                ->filter(fn ($item) => $item['url'] !== null)
                ->values();

            return ApiResponse::success($featuredMedia);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch featured media', [
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch featured media', 'FETCH_FAILED', 500);
        }
    }
}
