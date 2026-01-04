<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Resources\V1\CollectionResource;
use App\Domains\Memora\Resources\V1\MediaSetResource;
use App\Domains\Memora\Resources\V1\PublicCollectionResource;
use App\Domains\Memora\Services\MediaSetService;
use App\Http\Controllers\Controller;
use App\Models\GuestCollectionToken;
use App\Support\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public Collection Controller
 *
 * Handles public access to collections.
 * Collections are simpler than selections - they're view-only galleries.
 */
class PublicCollectionController extends Controller
{
    protected MediaSetService $mediaSetService;

    public function __construct(MediaSetService $mediaSetService)
    {
        $this->mediaSetService = $mediaSetService;
    }

    /**
     * Check collection status (public endpoint - no authentication required)
     * Returns status and ownership info for quick validation
     */
    public function checkStatus(Request $request, string $id): JsonResponse
    {
        try {
            $collection = MemoraCollection::query()
                ->where('uuid', $id)
                ->select('uuid', 'status', 'user_uuid', 'name')
                ->firstOrFail();

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $collection->user_uuid === $userUuid;
            }

            // Check if collection has password protection and email registration
            $settings = $collection->settings ?? [];
            $hasPassword = ! empty($settings['privacy']['collectionPasswordEnabled'] ?? $settings['privacy']['password'] ?? $settings['password'] ?? false);
            $emailRegistration = $settings['general']['emailRegistration'] ?? $settings['emailRegistration'] ?? false;

            return ApiResponse::success([
                'id' => $collection->uuid,
                'status' => $collection->status?->value ?? $collection->status,
                'name' => $collection->name,
                'isOwner' => $isOwner,
                'hasPassword' => $hasPassword,
                'emailRegistration' => $emailRegistration,
                'isAccessible' => ($collection->status?->value === 'active' || $collection->status === 'active') || (($collection->status?->value === 'draft' || $collection->status === 'draft') && $isOwner),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to check collection status', [
                'collection_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to check collection status', 'CHECK_FAILED', 500);
        }
    }

    /**
     * Get a collection (public endpoint - no authentication required for published collections)
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $collection = MemoraCollection::query()
                ->where('uuid', $id)
                ->with(['mediaSets' => function ($query) {
                    $query->withCount('media')->orderBy('order');
                }, 'preset', 'watermark'])
                ->firstOrFail();

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $collection->user_uuid === $userUuid;
            }

            $status = $collection->status?->value ?? $collection->status;

            // Allow access if collection is active (published) or if owner viewing draft
            if ($status !== 'active' && ! ($status === 'draft' && $isOwner)) {
                return ApiResponse::error('Collection is not accessible', 'COLLECTION_NOT_ACCESSIBLE', 403);
            }

            // Check password if required
            $settings = $collection->settings ?? [];
            $hasPasswordProtection = ! empty($settings['privacy']['collectionPasswordEnabled'] ?? $settings['privacy']['password'] ?? $settings['password'] ?? false);
            $password = $settings['privacy']['password'] ?? $settings['password'] ?? null;

            // Check if client is verified (clients don't need collection password)
            $isClientVerified = false;
            $token = $request->bearerToken() ?? $request->header('X-Guest-Token') ?? $request->query('guest_token');
            if ($token) {
                $clientToken = GuestCollectionToken::where('token', $token)
                    ->where('collection_uuid', $id)
                    ->where('expires_at', '>', now())
                    ->first();
                $isClientVerified = $clientToken !== null;
            }

            if ($hasPasswordProtection && $password && ! $isOwner && ! $isClientVerified) {
                // Check for guest token first
                $guestToken = null;

                if ($token) {
                    $guestToken = GuestCollectionToken::where('token', $token)
                        ->where('collection_uuid', $id)
                        ->where('expires_at', '>', now())
                        ->first();
                }

                // If no valid guest token, check password header
                if (! $guestToken) {
                    $providedPassword = $request->header('X-Collection-Password');
                    if (! $providedPassword || $providedPassword !== $password) {
                        return ApiResponse::error('Password required', 'PASSWORD_REQUIRED', 401);
                    }
                }
            }

            // Use the isClientVerified we already determined above
            if ($isOwner) {
                // Owner always has access to all sets
                $isClientVerified = true;
            }

            // Filter client-only sets from mediaSets if not verified client
            if (! $isClientVerified) {
                $clientOnlySets = $settings['privacy']['clientOnlySets'] ?? $settings['clientOnlySets'] ?? [];
                if (! empty($clientOnlySets)) {
                    $collection->load(['mediaSets' => function ($query) use ($clientOnlySets) {
                        $query->whereNotIn('uuid', $clientOnlySets)
                            ->withCount(['media' => function ($q) {
                                $q->whereNull('deleted_at')->where('is_private', false);
                            }])
                            ->orderBy('order');
                    }]);
                } else {
                    // Reload with private media filter
                    $collection->load(['mediaSets' => function ($query) {
                        $query->withCount(['media' => function ($q) {
                            $q->whereNull('deleted_at')->where('is_private', false);
                        }])
                            ->orderBy('order');
                    }]);
                }
            } else {
                // Reload with all media (including private)
                $collection->load(['mediaSets' => function ($query) {
                    $query->withCount(['media' => function ($q) {
                        $q->whereNull('deleted_at');
                    }])
                        ->orderBy('order');
                }]);
            }

            // Use PublicCollectionResource to exclude sensitive data
            // Only use CollectionResource if owner is viewing (for preview mode)
            if ($isOwner) {
                return ApiResponse::success(new CollectionResource($collection));
            }

            return ApiResponse::success(new PublicCollectionResource($collection));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch collection', [
                'collection_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch collection', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Verify password for a collection (public endpoint - no authentication required)
     */
    public function verifyPassword(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        try {
            $collection = MemoraCollection::query()
                ->where('uuid', $id)
                ->firstOrFail();

            $settings = $collection->settings ?? [];

            // Check if password protection is enabled
            $collectionPasswordEnabled = $settings['privacy']['collectionPasswordEnabled'] ?? false;
            $password = $settings['privacy']['password'] ?? $settings['password'] ?? null;

            // If password protection is not enabled or no password is set, allow access
            if (! $collectionPasswordEnabled || ! $password) {
                return ApiResponse::success(['verified' => true]);
            }

            // Verify the password
            $verified = $request->input('password') === $password;

            if ($verified) {
                // Generate guest token that expires in 30 minutes
                $guestToken = GuestCollectionToken::create([
                    'collection_uuid' => $id,
                    'expires_at' => Carbon::now()->addMinutes(30),
                ]);

                return ApiResponse::success([
                    'verified' => true,
                    'token' => $guestToken->token,
                ]);
            }

            return ApiResponse::success(['verified' => false]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to verify collection password', [
                'collection_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to verify password', 'VERIFY_FAILED', 500);
        }
    }

    /**
     * Verify download PIN for a collection (public endpoint - no authentication required)
     */
    public function verifyDownloadPin(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ]);

        try {
            $collection = MemoraCollection::query()
                ->where('uuid', $id)
                ->firstOrFail();

            $status = $collection->status?->value ?? $collection->status;

            // Only allow access if collection is active (published)
            if ($status !== 'active') {
                return ApiResponse::error('Collection is not accessible', 'COLLECTION_NOT_ACCESSIBLE', 403);
            }

            $settings = $collection->settings ?? [];
            $downloadPin = $settings['download']['downloadPin'] ?? null;
            $downloadPinEnabled = $settings['download']['downloadPinEnabled'] ?? false;

            if (! $downloadPinEnabled || ! $downloadPin) {
                return ApiResponse::success(['verified' => true]);
            }

            $verified = $request->input('pin') === $downloadPin;

            return ApiResponse::success(['verified' => $verified]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to verify download PIN', [
                'collection_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to verify download PIN', 'VERIFY_FAILED', 500);
        }
    }

    /**
     * Verify client password for a collection (public endpoint - no authentication required)
     */
    public function verifyClientPassword(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'email' => ['nullable', 'string', 'email'],
        ]);

        try {
            $collection = MemoraCollection::query()
                ->where('uuid', $id)
                ->firstOrFail();

            $status = $collection->status?->value ?? $collection->status;

            // Allow access if collection is active (published)
            if ($status !== 'active') {
                return ApiResponse::error('Collection is not accessible', 'COLLECTION_NOT_ACCESSIBLE', 403);
            }

            $settings = $collection->settings ?? [];
            $clientExclusiveAccess = $settings['privacy']['clientExclusiveAccess'] ?? $settings['clientExclusiveAccess'] ?? false;

            if (! $clientExclusiveAccess) {
                return ApiResponse::error('Client exclusive access is not enabled for this collection', 'CLIENT_ACCESS_DISABLED', 403);
            }

            $clientPrivatePassword = $settings['privacy']['clientPrivatePassword'] ?? $settings['clientPrivatePassword'] ?? null;

            if (! $clientPrivatePassword) {
                return ApiResponse::error('Client password is not set for this collection', 'CLIENT_PASSWORD_NOT_SET', 403);
            }

            // Verify the password
            $verified = $request->input('password') === $clientPrivatePassword;

            if ($verified) {
                // Generate guest token that expires in 30 minutes
                $guestToken = GuestCollectionToken::create([
                    'collection_uuid' => $id,
                    'expires_at' => Carbon::now()->addMinutes(30),
                ]);

                return ApiResponse::success([
                    'verified' => true,
                    'token' => $guestToken->token,
                ]);
            }

            return ApiResponse::success(['verified' => false]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to verify client password', [
                'collection_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to verify client password', 'VERIFY_FAILED', 500);
        }
    }

    /**
     * Get all media sets for a collection (public endpoint)
     */
    public function getSets(Request $request, string $id): JsonResponse
    {
        try {
            $collection = MemoraCollection::query()
                ->where('uuid', $id)
                ->firstOrFail();

            $status = $collection->status?->value ?? $collection->status;

            // Allow access if collection is active (published)
            if ($status !== 'active') {
                return ApiResponse::error('Collection is not accessible', 'COLLECTION_NOT_ACCESSIBLE', 403);
            }

            // Check if client is verified
            $isClientVerified = false;
            $token = $request->bearerToken() ?? $request->header('X-Guest-Token') ?? $request->query('guest_token');
            if ($token) {
                $guestToken = GuestCollectionToken::where('token', $token)
                    ->where('collection_uuid', $id)
                    ->where('expires_at', '>', now())
                    ->first();
                $isClientVerified = $guestToken !== null;
            }

            // Get client-only sets from settings
            $settings = $collection->settings ?? [];
            $clientOnlySets = $settings['privacy']['clientOnlySets'] ?? $settings['clientOnlySets'] ?? [];

            // Get sets without user authentication check (public access)
            $setsQuery = \App\Domains\Memora\Models\MemoraMediaSet::where('collection_uuid', $id);

            // Filter client-only sets: only show to verified clients
            if (! $isClientVerified && ! empty($clientOnlySets)) {
                $setsQuery->whereNotIn('uuid', $clientOnlySets);
            }

            $sets = $setsQuery->withCount(['media' => function ($query) use ($isClientVerified) {
                $query->whereNull('deleted_at');
                // Filter private media: only show to verified clients
                if (! $isClientVerified) {
                    $query->where('is_private', false);
                }
            }])
                ->orderBy('order')
                ->get();

            return ApiResponse::success(MediaSetResource::collection($sets));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch collection sets', [
                'collection_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch sets', 'FETCH_FAILED', 500);
        }
    }
}
