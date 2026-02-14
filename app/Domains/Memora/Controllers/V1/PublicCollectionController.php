<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraGuestCollectionToken;
use App\Domains\Memora\Resources\V1\CollectionResource;
use App\Domains\Memora\Resources\V1\MediaSetResource;
use App\Domains\Memora\Resources\V1\PublicCollectionResource;
use App\Domains\Memora\Services\MediaSetService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            $cacheKey = "public.collection.status:{$id}";
            $cached = Cache::remember($cacheKey, 120, function () use ($id) {
                $collection = MemoraCollection::query()
                    ->where('uuid', $id)
                    ->select('uuid', 'status', 'user_uuid', 'name', 'settings')
                    ->firstOrFail();
                $settings = $collection->settings ?? [];
                $hasPassword = ! empty($settings['privacy']['collectionPasswordEnabled'] ?? $settings['privacy']['password'] ?? $settings['password'] ?? false);
                $emailRegistration = $settings['general']['emailRegistration'] ?? $settings['emailRegistration'] ?? false;
                $status = $collection->status?->value ?? $collection->status;

                return [
                    'id' => $collection->uuid,
                    'status' => $status,
                    'user_uuid' => $collection->user_uuid,
                    'name' => $collection->name,
                    'hasPassword' => $hasPassword,
                    'emailRegistration' => $emailRegistration,
                ];
            });

            $isOwner = false;
            if (auth()->check()) {
                $isOwner = auth()->user()->uuid === ($cached['user_uuid'] ?? null);
            }
            unset($cached['user_uuid']);
            $cached['isOwner'] = $isOwner;
            $cached['isAccessible'] = ($cached['status'] === 'active') || (($cached['status'] === 'draft') && $isOwner);

            return ApiResponse::success($cached);
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
                $clientToken = MemoraGuestCollectionToken::where('token', $token)
                    ->where('collection_uuid', $id)
                    ->where('expires_at', '>', now())
                    ->first();
                $isClientVerified = $clientToken !== null;
            }

            if ($hasPasswordProtection && $password && ! $isOwner && ! $isClientVerified) {
                // Check for guest token first
                $guestToken = null;

                if ($token) {
                    $guestToken = MemoraGuestCollectionToken::where('token', $token)
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

            // Check if preview mode and owner
            $isPreviewMode = $request->query('preview') === 'true';
            $showPrivateMedia = $isOwner && $isPreviewMode;

            // Use the isClientVerified we already determined above
            if ($isOwner) {
                // Owner always has access to all sets
                $isClientVerified = true;
            }

            // Filter client-only sets from mediaSets if not verified client
            if (! $isClientVerified) {
                $clientOnlySets = $settings['privacy']['clientOnlySets'] ?? $settings['clientOnlySets'] ?? [];
                if (! empty($clientOnlySets)) {
                    $collection->load(['mediaSets' => function ($query) use ($clientOnlySets, $showPrivateMedia) {
                        $query->whereNotIn('uuid', $clientOnlySets)
                            ->withCount(['media' => function ($q) use ($showPrivateMedia) {
                                $q->whereNull('deleted_at');
                                if (! $showPrivateMedia) {
                                    $q->where('is_private', false);
                                }
                            }])
                            ->orderBy('order');
                    }]);
                } else {
                    // Reload with private media filter (unless owner in preview mode)
                    $collection->load(['mediaSets' => function ($query) use ($showPrivateMedia) {
                        $query->withCount(['media' => function ($q) use ($showPrivateMedia) {
                            $q->whereNull('deleted_at');
                            if (! $showPrivateMedia) {
                                $q->where('is_private', false);
                            }
                        }])
                            ->orderBy('order');
                    }]);
                }
            } else {
                // Reload with all media (including private) for verified clients or owner in preview
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
                $guestToken = MemoraGuestCollectionToken::create([
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
                $guestToken = MemoraGuestCollectionToken::create([
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
                $guestToken = MemoraGuestCollectionToken::where('token', $token)
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
                // Filter private media: only show to verified clients (public views don't show private media)
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

    /**
     * Initiate ZIP download generation
     */
    public function initiateZipDownload(Request $request, string $id): JsonResponse
    {
        try {
            // Validate collection access first (includes password, download PIN, email restrictions)
            $validationError = $this->validateCollectionAccess($request, $id);
            if ($validationError) {
                return $validationError;
            }

            $collection = MemoraCollection::where('uuid', $id)->firstOrFail();

            $status = $collection->status?->value ?? $collection->status;
            if ($status !== 'active') {
                return ApiResponse::error('Collection is not accessible', 'COLLECTION_NOT_ACCESSIBLE', 403);
            }

            $settings = $collection->settings ?? [];
            $downloadSettings = $settings['download'] ?? [];

            if (! ($downloadSettings['photoDownload'] ?? true)) {
                return ApiResponse::error('Downloads are disabled for this collection', 'DOWNLOADS_DISABLED', 403);
            }

            $validated = $request->validate([
                'setIds' => 'required|array',
                'setIds.*' => 'uuid',
                'size' => 'nullable|string',
                'destination' => 'nullable|string',
            ]);

            $token = bin2hex(random_bytes(16));
            $userEmail = $request->header('X-Collection-Email');
            $downloaderEmail = $userEmail;
            $destination = $validated['destination'] ?? 'device';

            // If cloud storage is selected, check if OAuth token exists and copy it to use download token
            if ($destination !== 'device') {
                $collectionTokenKey = "cloud_token_{$destination}_{$id}";
                $collectionTokenData = \Illuminate\Support\Facades\Cache::get($collectionTokenKey);

                if ($collectionTokenData && isset($collectionTokenData['access_token'])) {
                    // Copy token to use download token as key
                    $downloadTokenKey = "cloud_token_{$destination}_{$token}";
                    \Illuminate\Support\Facades\Cache::put($downloadTokenKey, $collectionTokenData, now()->addHours(24));

                    \Illuminate\Support\Facades\Log::info('OAuth token copied from collection_id to download token', [
                        'destination' => $destination,
                        'collection_id' => $id,
                        'download_token' => $token,
                    ]);
                }
            }

            // Store ZIP task in session/cache (in production, use database/redis)
            $zipTask = [
                'token' => $token,
                'collection_id' => $id,
                'set_ids' => $validated['setIds'],
                'size' => $validated['size'] ?? '3600px',
                'destination' => $destination,
                'email' => $userEmail,
                'status' => 'processing',
                'created_at' => now(),
            ];

            \Illuminate\Support\Facades\Cache::put("zip_download_{$token}", $zipTask, now()->addHours(24));

            // Dispatch job to generate ZIP
            \App\Domains\Memora\Jobs\GenerateZipDownloadJob::dispatch(
                $token,
                $id,
                $validated['setIds'],
                $validated['size'] ?? '3600px',
                $userEmail,
                $destination,
                $downloaderEmail
            );

            return ApiResponse::success([
                'token' => $token,
                'status' => 'processing',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to initiate ZIP download', [
                'collection_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to initiate download', 'INITIATE_FAILED', 500);
        }
    }

    /**
     * Get ZIP download status
     */
    public function getZipDownloadStatus(Request $request, string $id, string $token): JsonResponse
    {
        try {
            // Validate collection access first
            $validationError = $this->validateCollectionAccess($request, $id);
            if ($validationError) {
                return $validationError;
            }

            $zipTask = \Illuminate\Support\Facades\Cache::get("zip_download_{$token}");

            if (! $zipTask) {
                return ApiResponse::error('Download not found', 'NOT_FOUND', 404);
            }

            if ($zipTask['collection_id'] !== $id) {
                return ApiResponse::error('Invalid token', 'INVALID_TOKEN', 403);
            }

            // Verify email matches if stored in zipTask
            $storedEmail = $zipTask['email'] ?? null;
            $requestEmail = $request->header('X-Collection-Email');
            if ($storedEmail && $requestEmail && $storedEmail !== $requestEmail) {
                return ApiResponse::error('Email mismatch', 'EMAIL_MISMATCH', 403);
            }

            return ApiResponse::success([
                'status' => $zipTask['status'] ?? 'processing',
                'zipFile' => $zipTask['status'] === 'completed' ? [
                    'filename' => $zipTask['filename'] ?? null,
                    'size' => $zipTask['size'] ?? null,
                    'cloud_upload_url' => $zipTask['cloud_upload_url'] ?? null,
                    'cloud_upload_error' => $zipTask['cloud_upload_error'] ?? null,
                ] : null,
                'error' => $zipTask['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to get status', 'STATUS_FAILED', 500);
        }
    }

    /**
     * Download ZIP file
     */
    public function downloadZip(Request $request, string $id, string $token): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        try {
            // Validate collection access first
            $validationError = $this->validateCollectionAccess($request, $id);
            if ($validationError) {
                return $validationError;
            }

            $zipTask = \Illuminate\Support\Facades\Cache::get("zip_download_{$token}");

            if (! $zipTask) {
                \Illuminate\Support\Facades\Log::warning('ZIP download task not found', [
                    'token' => $token,
                    'collection_id' => $id,
                ]);

                return ApiResponse::error('Download not found', 'NOT_FOUND', 404);
            }

            if ($zipTask['status'] !== 'completed') {
                \Illuminate\Support\Facades\Log::info('ZIP download not ready', [
                    'token' => $token,
                    'status' => $zipTask['status'] ?? 'unknown',
                ]);

                return ApiResponse::error('Download not ready', 'NOT_READY', 404);
            }

            if (($zipTask['collection_id'] ?? null) !== $id) {
                \Illuminate\Support\Facades\Log::warning('ZIP download collection ID mismatch', [
                    'token' => $token,
                    'expected_id' => $id,
                    'cache_id' => $zipTask['collection_id'] ?? null,
                ]);

                return ApiResponse::error('Invalid token', 'INVALID_TOKEN', 403);
            }

            // Verify email matches if stored in zipTask
            $storedEmail = $zipTask['email'] ?? null;
            $requestEmail = $request->header('X-Collection-Email');
            if ($storedEmail && $requestEmail && $storedEmail !== $requestEmail) {
                return ApiResponse::error('Email mismatch', 'EMAIL_MISMATCH', 403);
            }

            // file_path is stored as "downloads/filename.zip" in the job
            $filePath = storage_path("app/{$zipTask['file_path']}");

            \Illuminate\Support\Facades\Log::info('Attempting ZIP download', [
                'token' => $token,
                'file_path' => $filePath,
                'file_path_from_cache' => $zipTask['file_path'] ?? null,
                'filename' => $zipTask['filename'] ?? null,
                'file_exists' => file_exists($filePath),
            ]);

            if (! file_exists($filePath)) {
                \Illuminate\Support\Facades\Log::error('ZIP file not found', [
                    'expected_path' => $filePath,
                    'file_path_from_cache' => $zipTask['file_path'] ?? null,
                    'filename' => $zipTask['filename'] ?? null,
                    'storage_app' => storage_path('app'),
                    'downloads_dir' => storage_path('app/downloads'),
                    'downloads_dir_exists' => is_dir(storage_path('app/downloads')),
                ]);

                return ApiResponse::error('File not found', 'FILE_NOT_FOUND', 404);
            }

            $filename = $zipTask['filename'] ?? 'download.zip';

            // Send notification to collection owner that collection has been downloaded
            // Only send if not the owner downloading their own collection
            try {
                $collection = MemoraCollection::where('uuid', $id)->first();
                if ($collection && $collection->user_uuid) {
                    $isOwner = false;
                    if (auth()->check()) {
                        $userUuid = auth()->user()->uuid;
                        $isOwner = $collection->user_uuid === $userUuid;
                    }

                    // Don't notify if owner is downloading their own collection
                    if (! $isOwner) {
                        $owner = \App\Models\User::where('uuid', $collection->user_uuid)->first();
                        if ($owner) {
                            $downloaderEmail = $zipTask['downloader_email'] ?? null;
                            $mediaCount = $zipTask['media_count'] ?? 0;
                            $size = $zipTask['resolution'] ?? '3600px';

                            $owner->notify(new \App\Notifications\CollectionDownloadedNotification(
                                $collection,
                                $downloaderEmail ?? '',
                                $mediaCount,
                                $size
                            ));

                            // Log activity for collection downloaded email notification
                            try {
                                app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                                    'notification_sent',
                                    $collection,
                                    'Collection downloaded email sent to owner',
                                    [
                                        'channel' => 'email',
                                        'notification' => 'CollectionDownloadedNotification',
                                        'owner_uuid' => $owner->uuid ?? null,
                                        'collection_uuid' => $collection->uuid ?? null,
                                        'downloader_email' => $downloaderEmail,
                                        'media_count' => $mediaCount,
                                        'download_size' => $size,
                                    ]
                                );
                            } catch (\Throwable $logException) {
                                \Illuminate\Support\Facades\Log::error('Failed to log collection downloaded notification activity', [
                                    'collection_uuid' => $collection->uuid ?? null,
                                    'owner_uuid' => $owner->uuid ?? null,
                                    'error' => $logException->getMessage(),
                                ]);
                            }

                            // Create in-app notification
                            $notificationService = app(\App\Services\Notification\NotificationService::class);
                            $brandingDomain = \App\Support\MemoraFrontendUrls::getBrandingDomainForUser($collection->user_uuid);
                            $domain = $brandingDomain ?? $collection->project_uuid ?? 'standalone';
                            $collectionUrl = \App\Support\MemoraFrontendUrls::publicCollectionFullUrl($domain, $collection->uuid);

                            $downloaderInfo = $downloaderEmail ? "by **{$downloaderEmail}**" : 'by a visitor';
                            $notificationService->create(
                                $collection->user_uuid,
                                'memora',
                                'collection_downloaded',
                                'Collection Downloaded',
                                "Your collection '{$collection->name}' has been downloaded {$downloaderInfo}.",
                                "Your collection **{$collection->name}** has been downloaded {$downloaderInfo}.",
                                null,
                                $collectionUrl,
                                [
                                    'collection_id' => $collection->uuid,
                                    'collection_name' => $collection->name,
                                    'downloader_email' => $downloaderEmail,
                                    'media_count' => $mediaCount,
                                    'download_size' => $size,
                                ]
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send collection download notification to owner', [
                    'collection_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/zip',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to download ZIP', [
                'token' => $token,
                'collection_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::error('Download failed: '.$e->getMessage(), 'DOWNLOAD_FAILED', 500);
        }
    }

    /**
     * Validate collection access (shared logic for download endpoints)
     * Validates guest token, password, download PIN, and email restrictions
     */
    private function validateCollectionAccess(Request $request, string $id): ?JsonResponse
    {
        try {
            $collection = MemoraCollection::where('uuid', $id)->firstOrFail();

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

            $settings = $collection->settings ?? [];

            // Check password protection
            $hasPasswordProtection = ! empty($settings['privacy']['collectionPasswordEnabled'] ?? $settings['privacy']['password'] ?? $settings['password'] ?? false);
            $password = $settings['privacy']['password'] ?? $settings['password'] ?? null;

            // Check if client is verified (clients don't need collection password)
            $isClientVerified = false;
            $token = $request->bearerToken() ?? $request->header('X-Guest-Token') ?? $request->query('guest_token');
            $guestToken = null;

            if ($token) {
                $guestToken = MemoraGuestCollectionToken::where('token', $token)
                    ->where('collection_uuid', $id)
                    ->where('expires_at', '>', now())
                    ->first();
                $isClientVerified = $guestToken !== null;
            }

            // Owner always has access
            if ($isOwner) {
                $isClientVerified = true;
            }

            // Check password if required and not owner/client verified
            if ($hasPasswordProtection && $password && ! $isOwner && ! $isClientVerified) {
                $providedPassword = $request->header('X-Collection-Password');
                if (! $providedPassword || $providedPassword !== $password) {
                    return ApiResponse::error('Password required', 'PASSWORD_REQUIRED', 401);
                }
            }

            // Check download settings
            $downloadSettings = $settings['download'] ?? [];

            // Check if downloads are enabled
            if (! ($downloadSettings['photoDownload'] ?? true)) {
                return ApiResponse::error('Downloads are disabled for this collection', 'DOWNLOADS_DISABLED', 403);
            }

            // Check download PIN
            $downloadPinEnabled = $downloadSettings['downloadPinEnabled'] ?? false;
            $downloadPin = $downloadSettings['downloadPin'] ?? null;
            if ($downloadPinEnabled && $downloadPin && ! $isOwner) {
                $providedPin = $request->header('X-Download-PIN');
                if (! $providedPin || $providedPin !== $downloadPin) {
                    return ApiResponse::error('Download PIN required', 'DOWNLOAD_PIN_REQUIRED', 401);
                }
            }

            // Check email restrictions
            $restrictToContacts = $downloadSettings['restrictToContacts'] ?? false;
            $allowedEmails = $downloadSettings['allowedDownloadEmails'] ?? null;

            if ($restrictToContacts && is_array($allowedEmails) && count($allowedEmails) > 0 && ! $isOwner) {
                $userEmail = $request->header('X-Collection-Email');
                if (! $userEmail || ! in_array(strtolower($userEmail), array_map('strtolower', $allowedEmails))) {
                    return ApiResponse::error('Email not authorized for download', 'EMAIL_NOT_AUTHORIZED', 403);
                }
            }

            return null; // Validation passed
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Collection not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to validate collection access', [
                'collection_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to validate access', 'VALIDATION_FAILED', 500);
        }
    }
}
