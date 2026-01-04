<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraCollectionDownload;
use App\Domains\Memora\Models\MemoraCollectionEmailRegistration;
use App\Domains\Memora\Models\MemoraCollectionFavourite;
use App\Domains\Memora\Models\MemoraCollectionPrivatePhotoAccess;
use App\Domains\Memora\Models\MemoraCollectionShareLink;
use App\Services\ActivityLog\ActivityLogService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CollectionActivityController extends Controller
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Track email registration for a collection
     */
    public function trackEmailRegistration(Request $request, string $collectionId): JsonResponse
    {
        try {
            $collection = MemoraCollection::where('uuid', $collectionId)->firstOrFail();

            $request->validate([
                'email' => ['required', 'email'],
                'name' => ['nullable', 'string', 'max:255'],
            ]);

            $email = strtolower(trim($request->input('email')));
            $name = $request->input('name');
            $userUuid = auth()->check() ? auth()->user()->uuid : null;

            // Check if email already registered for this collection
            $existingRegistration = MemoraCollectionEmailRegistration::where('collection_uuid', $collectionId)
                ->where('email', $email)
                ->first();

            $isNewRegistration = false;
            if ($existingRegistration) {
                // Update last access info
                $existingRegistration->ip_address = $request->ip();
                $existingRegistration->user_agent = $request->userAgent();
                $existingRegistration->last_access_at = now();
                $existingRegistration->save();
            } else {
                // Create new registration only if it doesn't exist
                MemoraCollectionEmailRegistration::create([
                    'collection_uuid' => $collectionId,
                    'email' => $email,
                    'name' => $name,
                    'user_uuid' => $userUuid,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'last_access_at' => now(),
                ]);
                $isNewRegistration = true;
            }

            // Only log activity for new registrations
            if ($isNewRegistration) {
                try {
                    $this->activityLogService->logCustom(
                        'email_registered',
                        "Email registered for collection: {$email}",
                        [
                            'collection_uuid' => $collectionId,
                            'email' => $email,
                            'name' => $name,
                        ],
                        $userUuid ? \App\Models\User::find($userUuid) : null,
                        $request
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to log email registration activity', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ApiResponse::success(['message' => 'Email registration tracked']);
        } catch (\Exception $e) {
            Log::error('Failed to track email registration', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to track email registration', 'TRACKING_FAILED', 500);
        }
    }

    /**
     * Track quick share link click
     */
    public function trackShareLinkClick(Request $request, string $collectionId): JsonResponse
    {
        try {
            $collection = MemoraCollection::where('uuid', $collectionId)->firstOrFail();

            $request->validate([
                'link_id' => ['nullable', 'string'],
                'link_url' => ['nullable', 'string'],
            ]);

            $userEmail = $request->header('X-Collection-Email');
            $userUuid = auth()->check() ? auth()->user()->uuid : null;
            $linkId = $request->input('link_id');
            $linkUrl = $request->input('link_url');

            // Check if this exact link already exists for this collection
            $existingLink = MemoraCollectionShareLink::where('collection_uuid', $collectionId)
                ->where(function ($query) use ($linkId, $linkUrl) {
                    if ($linkId) {
                        $query->where('link_id', $linkId);
                    } elseif ($linkUrl) {
                        $query->where('link_url', $linkUrl);
                    }
                })
                ->first();

            if (!$existingLink) {
                // Only create if this link doesn't exist yet
                MemoraCollectionShareLink::create([
                    'collection_uuid' => $collectionId,
                    'link_id' => $linkId,
                    'link_url' => $linkUrl,
                    'email' => $userEmail ? strtolower(trim($userEmail)) : null,
                    'user_uuid' => $userUuid,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } else {
                // Update existing link with latest access info
                $existingLink->ip_address = $request->ip();
                $existingLink->user_agent = $request->userAgent();
                if ($userEmail) {
                    $existingLink->email = strtolower(trim($userEmail));
                }
                $existingLink->save();
            }

            try {
                $this->activityLogService->logCustom(
                    'share_link_clicked',
                    'Quick share link clicked',
                    [
                        'collection_uuid' => $collectionId,
                        'link_id' => $request->input('link_id'),
                        'link_url' => $request->input('link_url'),
                    ],
                    $userUuid ? \App\Models\User::find($userUuid) : null,
                    $request
                );
            } catch (\Exception $e) {
                Log::warning('Failed to log share link activity', [
                    'error' => $e->getMessage(),
                ]);
            }

            return ApiResponse::success(['message' => 'Share link click tracked']);
        } catch (\Exception $e) {
            Log::error('Failed to track share link click', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to track share link click', 'TRACKING_FAILED', 500);
        }
    }

    /**
     * Track private photo access
     */
    public function trackPrivatePhotoAccess(Request $request, string $collectionId, string $mediaId): JsonResponse
    {
        try {
            $collection = MemoraCollection::where('uuid', $collectionId)->firstOrFail();

            $userEmail = $request->header('X-Collection-Email');
            $userUuid = auth()->check() ? auth()->user()->uuid : null;
            $email = $userEmail ? strtolower(trim($userEmail)) : null;

            // Check if tracking record already exists for this email and media
            $existingRecord = MemoraCollectionPrivatePhotoAccess::where('collection_uuid', $collectionId)
                ->where('media_uuid', $mediaId)
                ->where('email', $email)
                ->first();

            if ($existingRecord) {
                // Update existing record
                $existingRecord->ip_address = $request->ip();
                $existingRecord->user_agent = $request->userAgent();
                $existingRecord->touch();
                $existingRecord->save();
            } else {
                // Create new record only if it doesn't exist
                MemoraCollectionPrivatePhotoAccess::create([
                    'collection_uuid' => $collectionId,
                    'media_uuid' => $mediaId,
                    'email' => $email,
                    'user_uuid' => $userUuid,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            try {
                $this->activityLogService->logCustom(
                    'private_photo_accessed',
                    'Private photo accessed',
                    [
                        'collection_uuid' => $collectionId,
                        'media_uuid' => $mediaId,
                        'email' => $email,
                        'user_uuid' => $userUuid,
                    ],
                    $userUuid ? \App\Models\User::find($userUuid) : null,
                    $request
                );
            } catch (\Exception $e) {
                Log::warning('Failed to log private photo access activity', [
                    'error' => $e->getMessage(),
                ]);
            }

            return ApiResponse::success(['message' => 'Private photo access tracked']);
        } catch (\Exception $e) {
            Log::error('Failed to track private photo access', [
                'collection_id' => $collectionId,
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to track private photo access', 'TRACKING_FAILED', 500);
        }
    }

    /**
     * Get email registrations for a collection
     */
    public function getEmailRegistrations(Request $request, string $collectionId): JsonResponse
    {
        try {
            $collection = MemoraCollection::where('uuid', $collectionId)->firstOrFail();

            $registrations = MemoraCollectionEmailRegistration::where('collection_uuid', $collectionId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($registration) {
                    return [
                        'id' => $registration->uuid,
                        'email' => $registration->email,
                        'name' => $registration->name,
                        'registeredAt' => $registration->created_at->toISOString(),
                        'verified' => false,
                        'lastAccessAt' => $registration->last_access_at ? $registration->last_access_at->toISOString() : null,
                    ];
                });

            return ApiResponse::success($registrations);
        } catch (\Exception $e) {
            Log::error('Failed to fetch email registrations', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch email registrations', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Get quick share link activities
     */
    public function getShareLinkActivities(Request $request, string $collectionId): JsonResponse
    {
        try {
            $collection = MemoraCollection::where('uuid', $collectionId)->firstOrFail();

            $links = MemoraCollectionShareLink::where('collection_uuid', $collectionId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(function ($link) {
                    return $link->link_id ?? $link->link_url ?? 'unknown';
                })
                ->map(function ($group, $linkId) {
                    $first = $group->first();
                    return [
                        'id' => $linkId,
                        'name' => $first->link_id ?? 'Untitled Link',
                        'url' => $first->link_url ?? '',
                        'active' => true,
                        'createdAt' => $first->created_at->toISOString(),
                        'clickCount' => $group->count(),
                        'lastUsedAt' => $group->max('created_at')?->toISOString(),
                    ];
                })
                ->values();

            return ApiResponse::success($links);
        } catch (\Exception $e) {
            Log::error('Failed to fetch share link activities', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch share link activities', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Get download activities
     */
    public function getDownloadActivities(Request $request, string $collectionId): JsonResponse
    {
        try {
            $collection = MemoraCollection::where('uuid', $collectionId)->firstOrFail();

            $downloads = MemoraCollectionDownload::where('collection_uuid', $collectionId)
                ->with(['media.file'])
                ->orderBy('created_at', 'desc')
                ->get();

            $activities = $downloads->map(function ($download) {
                $media = $download->media;
                $file = $media->file ?? null;
                
                $photoName = 'Unknown';
                $mediaType = null;
                if ($file) {
                    $photoName = $file->filename ?? 'Unknown';
                    $mediaType = $file->type?->value ?? $file->type;
                }
                
                $photoThumbnail = null;
                if ($file) {
                    $fileType = $file->type?->value ?? $file->type;
                    if ($fileType === 'image' && $file->metadata && isset($file->metadata['variants']['thumb'])) {
                        $photoThumbnail = $file->metadata['variants']['thumb'];
                    } elseif ($fileType === 'video' && $file->metadata) {
                        $photoThumbnail = $file->metadata['thumbnail'] ?? $file->metadata['variants']['thumb'] ?? null;
                    } else {
                        $photoThumbnail = $media->thumbnail_url ?? $file->url ?? null;
                    }
                }
                
                return [
                    'id' => $download->uuid,
                    'mediaId' => $media?->uuid ?? null,
                    'setId' => $media?->media_set_uuid ?? null,
                    'timestamp' => $download->created_at->toISOString(),
                    'userEmail' => $download->email,
                    'userName' => null,
                    'photoName' => $photoName,
                    'photoThumbnail' => $photoThumbnail,
                    'mediaType' => $mediaType,
                    'isVideo' => $mediaType === 'video',
                    'downloadType' => $download->download_type === 'full' ? 'full' : 'web',
                    'ipAddress' => $download->ip_address,
                ];
            });

            return ApiResponse::success($activities);
        } catch (\Exception $e) {
            Log::error('Failed to fetch download activities', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch download activities', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Get favourite activities
     */
    public function getFavouriteActivities(Request $request, string $collectionId): JsonResponse
    {
        try {
            $collection = MemoraCollection::where('uuid', $collectionId)->firstOrFail();

            $favourites = MemoraCollectionFavourite::where('collection_uuid', $collectionId)
                ->with(['media.file'])
                ->orderBy('created_at', 'desc')
                ->get();

            $activities = $favourites->map(function ($favourite) {
                $media = $favourite->media;
                $file = $media->file ?? null;
                
                $photoName = 'Unknown';
                $mediaType = null;
                if ($file) {
                    $photoName = $file->filename ?? 'Unknown';
                    $mediaType = $file->type?->value ?? $file->type;
                }
                
                $photoThumbnail = null;
                if ($file) {
                    $fileType = $file->type?->value ?? $file->type;
                    if ($fileType === 'image' && $file->metadata && isset($file->metadata['variants']['thumb'])) {
                        $photoThumbnail = $file->metadata['variants']['thumb'];
                    } elseif ($fileType === 'video' && $file->metadata) {
                        $photoThumbnail = $file->metadata['thumbnail'] ?? $file->metadata['variants']['thumb'] ?? null;
                    } else {
                        $photoThumbnail = $media->thumbnail_url ?? $file->url ?? null;
                    }
                }
                
                return [
                    'id' => $favourite->uuid,
                    'mediaId' => $media?->uuid ?? null,
                    'setId' => $media?->media_set_uuid ?? null,
                    'timestamp' => $favourite->created_at->toISOString(),
                    'userEmail' => $favourite->email,
                    'userName' => null,
                    'photoName' => $photoName,
                    'photoThumbnail' => $photoThumbnail,
                    'mediaType' => $mediaType,
                    'isVideo' => $mediaType === 'video',
                    'action' => 'favourite',
                ];
            });

            return ApiResponse::success($activities);
        } catch (\Exception $e) {
            Log::error('Failed to fetch favourite activities', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch favourite activities', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Get private photo activities
     */
    public function getPrivatePhotoActivities(Request $request, string $collectionId): JsonResponse
    {
        try {
            $collection = MemoraCollection::where('uuid', $collectionId)->firstOrFail();

            $accesses = MemoraCollectionPrivatePhotoAccess::where('collection_uuid', $collectionId)
                ->with(['media.file'])
                ->orderBy('created_at', 'desc')
                ->get();

            $results = $accesses->map(function ($access) {
                $media = $access->media;
                $file = $media->file ?? null;
                
                $photoName = 'Unknown';
                $photoThumbnail = null;
                $mediaType = null;
                $isVideo = false;
                
                if ($file) {
                    $photoName = $file->filename ?? 'Unknown';
                    $mediaType = $file->type?->value ?? $file->type;
                    $isVideo = $mediaType === 'video';
                    
                    $fileType = $file->type?->value ?? $file->type;
                    if ($fileType === 'image' && $file->metadata && isset($file->metadata['variants']['thumb'])) {
                        $photoThumbnail = $file->metadata['variants']['thumb'];
                    } elseif ($fileType === 'video' && $file->metadata) {
                        $photoThumbnail = $file->metadata['thumbnail'] ?? $file->metadata['variants']['thumb'] ?? null;
                    } else {
                        $photoThumbnail = $media->thumbnail_url ?? $file->url ?? null;
                    }
                }
                
                return [
                    'id' => $access->uuid,
                    'mediaId' => $media?->uuid ?? null,
                    'setId' => $media?->media_set_uuid ?? null,
                    'timestamp' => $access->updated_at->toISOString(),
                    'userEmail' => $access->email,
                    'userName' => null,
                    'photoName' => $photoName,
                    'photoThumbnail' => $photoThumbnail,
                    'mediaType' => $mediaType,
                    'isVideo' => $isVideo,
                    'accessType' => 'granted',
                    'duration' => null,
                ];
            });

            return ApiResponse::success($results);
        } catch (\Exception $e) {
            Log::error('Failed to fetch private photo activities', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch private photo activities', 'FETCH_FAILED', 500);
        }
    }
}

