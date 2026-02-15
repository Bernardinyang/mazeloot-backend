<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraPreset;
use App\Domains\Memora\Models\MemoraProject;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Notification\NotificationService;
use App\Services\Pagination\PaginationService;
use App\Support\MemoraFrontendUrls;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CollectionService
{
    protected PaginationService $paginationService;

    protected NotificationService $notificationService;

    protected ActivityLogService $activityLogService;

    public function __construct(
        PaginationService $paginationService,
        NotificationService $notificationService,
        ActivityLogService $activityLogService
    ) {
        $this->paginationService = $paginationService;
        $this->notificationService = $notificationService;
        $this->activityLogService = $activityLogService;
    }

    /**
     * List collections (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, lists collections for that project. If null, lists all user collections.
     * @param  bool|null  $starred  Filter by starred status
     * @param  string|null  $search  Search query (searches in name)
     * @param  string|null  $sortBy  Sort field and direction (e.g., 'created-desc', 'name-asc')
     * @return array Paginated response with data and pagination metadata
     */
    public function list(?string $projectId, ?bool $starred = null, ?string $search = null, ?string $sortBy = null, ?int $page = null, ?int $perPage = null)
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraCollection::where('user_uuid', $user->uuid)
            ->with(['preset', 'watermark', 'project'])
            ->with(['starredByUsers' => function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            }])
            // Add subqueries for media and set counts to avoid N+1 queries
            ->addSelect([
                'media_count' => MemoraMedia::query()->selectRaw('COALESCE(COUNT(*), 0)')
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->whereColumn('memora_media_sets.collection_uuid', 'memora_collections.uuid')
                    ->limit(1),
                'set_count' => MemoraMediaSet::query()->selectRaw('COALESCE(COUNT(*), 0)')
                    ->whereColumn('collection_uuid', 'memora_collections.uuid')
                    ->limit(1),
            ]);

        if ($projectId) {
            // Validate project exists and belongs to user
            $project = MemoraProject::where('uuid', $projectId)
                ->where('user_uuid', $user->uuid)
                ->firstOrFail();
            $query->where('project_uuid', $projectId);
        }
        // When projectId is null, show all collections (both standalone and project-based)

        // Search by name
        if ($search && trim($search)) {
            $query->where('name', 'LIKE', '%'.trim($search).'%');
        }

        // Filter by starred status
        if ($starred !== null) {
            if ($starred) {
                // Only get collections that are starred by the current user
                $query->whereHas('starredByUsers', function ($q) use ($user) {
                    $q->where('user_uuid', $user->uuid);
                });
            } else {
                // Only get collections that are NOT starred by the current user
                $query->whereDoesntHave('starredByUsers', function ($q) use ($user) {
                    $q->where('user_uuid', $user->uuid);
                });
            }
        }

        // Apply sorting
        if ($sortBy) {
            $parts = explode('-', $sortBy);
            $field = $parts[0] ?? 'created';
            $direction = strtoupper($parts[1] ?? 'desc');

            // Validate direction - only use first two parts, ignore any additional parts
            if (! in_array($direction, ['ASC', 'DESC'])) {
                $direction = 'DESC';
            }

            // Map frontend field names to database columns
            $fieldMap = [
                'created' => 'created_at',
                'name' => 'name',
                'status' => 'status',
            ];

            $dbField = $fieldMap[$field] ?? 'created_at';
            $query->orderBy($dbField, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Paginate the query
        $perPage = $perPage ?? 10;
        $paginator = $this->paginationService->paginate($query, $perPage, $page);

        // Map the subquery results to the expected attribute names
        $paginator->getCollection()->each(function ($collection) {
            $collection->setAttribute('media_count', (int) ($collection->media_count ?? 0));
            $collection->setAttribute('set_count', (int) ($collection->set_count ?? 0));
        });

        // Transform items to resources
        $data = \App\Domains\Memora\Resources\V1\CollectionResource::collection($paginator->items());

        // Format response with pagination metadata
        return [
            'data' => $data,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Organize settings into structured format for storage
     */
    private function organizeSettingsForStorage(array $settings): array
    {
        $organized = [];

        // Preserve metadata fields
        if (isset($settings['eventDate'])) {
            $organized['eventDate'] = $settings['eventDate'];
        }
        if (isset($settings['display_settings'])) {
            $organized['display_settings'] = $settings['display_settings'];
        }
        if (isset($settings['thumbnail'])) {
            $organized['thumbnail'] = $settings['thumbnail'];
        }
        if (isset($settings['image'])) {
            $organized['image'] = $settings['image'];
        }

        // General settings
        $organized['general'] = [
            'url' => $settings['url'] ?? $settings['general']['url'] ?? null,
            'tags' => $settings['tags'] ?? $settings['general']['tags'] ?? [],
            'emailRegistration' => $settings['emailRegistration'] ?? $settings['general']['emailRegistration'] ?? false,
            'galleryAssist' => $settings['galleryAssist'] ?? $settings['general']['galleryAssist'] ?? false,
            'slideshow' => $settings['slideshow'] ?? $settings['general']['slideshow'] ?? true,
            'slideshowSpeed' => $settings['slideshowSpeed'] ?? $settings['general']['slideshowSpeed'] ?? 'regular',
            'slideshowAutoLoop' => $settings['slideshowAutoLoop'] ?? $settings['general']['slideshowAutoLoop'] ?? true,
            'socialSharing' => $settings['socialSharing'] ?? $settings['general']['socialSharing'] ?? true,
            'language' => $settings['language'] ?? $settings['general']['language'] ?? 'en',
            'autoExpiryDate' => $settings['autoExpiryDate'] ?? $settings['general']['autoExpiryDate'] ?? null,
            'expiryDate' => $settings['expiryDate'] ?? $settings['general']['expiryDate'] ?? null,
            'expiryDays' => $settings['expiryDays'] ?? $settings['general']['expiryDays'] ?? null,
        ];
        // Remove slideshowOptions if it exists
        unset($organized['general']['slideshowOptions']);

        // Privacy settings (use nested if exists, otherwise build from flat)
        if (isset($settings['privacy']) && is_array($settings['privacy'])) {
            $organized['privacy'] = $settings['privacy'];
            // Merge flat keys into privacy structure
            if (isset($settings['password'])) {
                $organized['privacy']['password'] = $settings['password'];
                $organized['privacy']['collectionPasswordEnabled'] = ! empty($settings['password']);
            }
            if (isset($settings['showOnHomepage'])) {
                $organized['privacy']['showOnHomepage'] = $settings['showOnHomepage'];
            }
            if (isset($settings['clientExclusiveAccess'])) {
                $organized['privacy']['clientExclusiveAccess'] = $settings['clientExclusiveAccess'];
            }
            if (isset($settings['clientPrivatePassword'])) {
                $organized['privacy']['clientPrivatePassword'] = $settings['clientPrivatePassword'];
            }
            if (isset($settings['allowClientsMarkPrivate'])) {
                $organized['privacy']['allowClientsMarkPrivate'] = $settings['allowClientsMarkPrivate'];
            }
            if (isset($settings['clientOnlySets'])) {
                $organized['privacy']['clientOnlySets'] = $settings['clientOnlySets'];
            }
            // Ensure collectionPasswordEnabled is set correctly
            if (! isset($organized['privacy']['collectionPasswordEnabled'])) {
                $organized['privacy']['collectionPasswordEnabled'] = ! empty($organized['privacy']['password'] ?? $settings['password'] ?? null);
            }
        } else {
            $organized['privacy'] = [
                'collectionPasswordEnabled' => ! empty($settings['password']),
                'password' => $settings['password'] ?? null, // Keep password in privacy.password
                'showOnHomepage' => $settings['showOnHomepage'] ?? false,
                'clientExclusiveAccess' => $settings['clientExclusiveAccess'] ?? false,
                'clientPrivatePassword' => $settings['clientPrivatePassword'] ?? null,
                'allowClientsMarkPrivate' => $settings['allowClientsMarkPrivate'] ?? false,
                'clientOnlySets' => $settings['clientOnlySets'] ?? null,
            ];
        }

        // Download settings (use nested if exists, otherwise build from flat)
        if (isset($settings['download']) && is_array($settings['download'])) {
            $organized['download'] = $settings['download'];
        } else {
            $organized['download'] = [
                'photoDownload' => $settings['photoDownload'] ?? true,
                'highResolution' => [
                    'enabled' => $settings['highResolutionEnabled'] ?? false,
                    'size' => $settings['highResolutionSize'] ?? '3600px',
                ],
                'webSize' => [
                    'enabled' => $settings['webSizeEnabled'] ?? false,
                    'size' => $settings['webSize'] ?? '1024px',
                ],
                'videoDownload' => $settings['videoDownload'] ?? false,
                'downloadPin' => $settings['downloadPin'] ?? null,
                'downloadPinEnabled' => $settings['downloadPinEnabled'] ?? false,
                'limitDownloads' => $settings['limitDownloads'] ?? false,
                'downloadLimit' => $settings['downloadLimit'] ?? 1,
                'restrictToContacts' => $settings['restrictToContacts'] ?? false,
                'allowedDownloadEmails' => $settings['allowedDownloadEmails'] ?? null,
                'downloadableSets' => $settings['downloadableSets'] ?? null,
            ];
        }

        // Favorite settings (use nested if exists, otherwise build from flat)
        if (isset($settings['favorite']) && is_array($settings['favorite'])) {
            $organized['favorite'] = $settings['favorite'];
        } else {
            $organized['favorite'] = [
                'enabled' => $settings['favoriteEnabled'] ?? true,
                'photos' => $settings['favoritePhotos'] ?? true,
                'notes' => $settings['favoriteNotes'] ?? true,
            ];
        }

        // Design settings - organize into nested structure
        $organized['design'] = [
            'cover' => $settings['design']['cover'] ?? $settings['coverDesign'] ?? [
                'coverLayoutUuid' => null,
                'coverFocalPoint' => ['x' => 50, 'y' => 50],
            ],
            'grid' => $settings['design']['grid'] ?? $settings['gridDesign'] ?? [
                'gridStyle' => 'grid',
                'gridColumns' => 3,
                'thumbnailOrientation' => 'medium',
                'gridSpacing' => 'normal',
                'tabStyle' => 'icon-text',
            ],
            'typography' => $settings['design']['typography'] ?? $settings['typographyDesign'] ?? [
                'fontFamily' => 'sans',
                'fontStyle' => 'normal',
            ],
            'color' => $settings['design']['color'] ?? $settings['colorDesign'] ?? [
                'colorPalette' => 'light',
            ],
        ];

        return $organized;
    }

    /**
     * Apply preset defaults to collection settings array
     */
    public function applyPresetDefaults(MemoraPreset $preset, array $existingSettings = []): array
    {
        $settings = $existingSettings;

        // Preserve metadata
        $eventDate = $settings['eventDate'] ?? null;
        $displaySettings = $settings['display_settings'] ?? null;
        $thumbnail = $settings['thumbnail'] ?? null;
        $image = $settings['image'] ?? null;

        // Build organized settings structure
        $settings = [
            'general' => [
                'url' => $settings['general']['url'] ?? $settings['url'] ?? null,
                'tags' => $settings['general']['tags'] ?? $settings['tags'] ?? ($preset->collection_tags ?? []),
                'emailRegistration' => $preset->email_registration ?? false,
                'galleryAssist' => $preset->gallery_assist ?? false,
                'slideshow' => $preset->slideshow ?? true,
                'slideshowSpeed' => $preset->slideshow_speed ?? 'regular',
                'slideshowAutoLoop' => $preset->slideshow_auto_loop ?? true,
                'socialSharing' => $preset->social_sharing ?? true,
                'language' => $preset->language ?? 'en',
                'autoExpiryDate' => $settings['general']['autoExpiryDate'] ?? $settings['autoExpiryDate'] ?? null,
                'expiryDate' => $settings['general']['expiryDate'] ?? $settings['expiryDate'] ?? null,
                'expiryDays' => $settings['general']['expiryDays'] ?? $settings['expiryDays'] ?? null,
            ],
            'privacy' => [
                'collectionPassword' => $preset->privacy_collection_password ?? false,
                'showOnHomepage' => $preset->privacy_show_on_homepage ?? false,
                'clientExclusiveAccess' => $preset->privacy_client_exclusive_access ?? false,
                'allowClientsMarkPrivate' => $preset->privacy_allow_clients_mark_private ?? false,
                'clientOnlySets' => $preset->privacy_client_only_sets,
            ],
            'download' => [
                'photoDownload' => $preset->download_photo_download ?? false,
                'highResolution' => [
                    'enabled' => $preset->download_high_resolution_enabled ?? false,
                    'size' => $preset->download_high_resolution_size ?? '3600px',
                ],
                'webSize' => [
                    'enabled' => $preset->download_web_size_enabled ?? false,
                    'size' => $preset->download_web_size ?? '1920px',
                ],
                'videoDownload' => $preset->download_video_download ?? false,
                'downloadPin' => $preset->download_download_pin ?? null,
                'downloadPinEnabled' => $preset->download_download_pin_enabled ?? false,
                'limitDownloads' => $preset->download_limit_downloads ?? false,
                'downloadLimit' => $preset->download_download_limit,
                'restrictToContacts' => $preset->download_restrict_to_contacts ?? false,
                'downloadableSets' => $preset->download_downloadable_sets,
            ],
            'favorite' => [
                'enabled' => $preset->favorite_favorite_enabled ?? false,
                'photos' => $preset->favorite_favorite_photos ?? false,
                'notes' => $preset->favorite_favorite_notes ?? false,
            ],
        ];
        // Remove slideshowOptions if it exists
        unset($settings['general']['slideshowOptions']);

        // Preserve design settings if they exist
        if (isset($existingSettings['design'])) {
            $settings['design'] = $existingSettings['design'];
        } elseif (isset($existingSettings['coverDesign']) || isset($existingSettings['gridDesign']) || isset($existingSettings['typographyDesign']) || isset($existingSettings['colorDesign'])) {
            $settings['design'] = [
                'cover' => $existingSettings['coverDesign'] ?? ['coverLayoutUuid' => null, 'coverFocalPoint' => ['x' => 50, 'y' => 50]],
                'grid' => $existingSettings['gridDesign'] ?? [],
                'typography' => $existingSettings['typographyDesign'] ?? [],
                'color' => $existingSettings['colorDesign'] ?? [],
            ];
        }

        // Restore metadata
        if ($eventDate !== null) {
            $settings['eventDate'] = $eventDate;
        }
        if ($displaySettings !== null) {
            $settings['display_settings'] = $displaySettings;
        }
        if ($thumbnail !== null) {
            $settings['thumbnail'] = $thumbnail;
        }
        if ($image !== null) {
            $settings['image'] = $image;
        }

        return $settings;
    }

    /**
     * Create a collection (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, creates collection for that project. If null, creates standalone collection.
     */
    public function create(?string $projectId, array $data): MemoraCollection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        if (! $user->isAdmin()) {
            $collectionLimit = app(\App\Services\Subscription\TierService::class)->getCollectionLimit($user);
            if ($collectionLimit !== null) {
                $currentCount = MemoraCollection::where('user_uuid', $user->uuid)->count();
                if ($currentCount >= $collectionLimit) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'limit' => ['Collection limit reached. Upgrade your plan for more collections.'],
                    ]);
                }
            }
        }

        $project = null;
        if ($projectId) {
            // Validate project exists and belongs to user
            $project = MemoraProject::where('uuid', $projectId)
                ->where('user_uuid', $user->uuid)
                ->firstOrFail();
        }

        $preset = null;
        $settings = $data['settings'] ?? [];
        $watermarkUuid = $data['watermarkId'] ?? null;

        // If preset provided, load it and apply defaults
        if (! empty($data['presetId'])) {
            $preset = MemoraPreset::where('uuid', $data['presetId'])
                ->where('user_uuid', $user->uuid)
                ->firstOrFail();

            $settings = $this->applyPresetDefaults($preset, $settings);

            // Apply default watermark from preset if not explicitly set
            if (! $watermarkUuid && $preset->default_watermark_uuid) {
                $watermarkUuid = $preset->default_watermark_uuid;
            }
        }

        // Handle eventDate in settings
        if (isset($data['eventDate'])) {
            if ($data['eventDate'] === null) {
                unset($settings['eventDate']);
            } else {
                $settings['eventDate'] = $data['eventDate'];
            }
        }

        // Organize settings into structured format before saving
        $organizedSettings = $this->organizeSettingsForStorage($settings);

        $collection = MemoraCollection::create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $projectId,
            'preset_uuid' => $data['presetId'] ?? null,
            'watermark_uuid' => $watermarkUuid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'color' => $data['color'] ?? $project?->color ?? '#8B5CF6',
            'settings' => $organizedSettings,
        ]);

        // Create media sets from preset photo_sets if preset is selected and has sets
        $setLimitPerPhase = app(\App\Services\Subscription\TierService::class)->getSetLimitPerPhase($user);
        $photoSets = ($preset && $preset->photo_sets && is_array($preset->photo_sets)) ? $preset->photo_sets : [];
        if ($photoSets !== []) {
            $toCreate = $setLimitPerPhase !== null
                ? array_slice($photoSets, 0, $setLimitPerPhase, true)
                : $photoSets;
            foreach ($toCreate as $index => $setName) {
                MemoraMediaSet::create([
                    'user_uuid' => $user->uuid,
                    'collection_uuid' => $collection->uuid,
                    'project_uuid' => $projectId,
                    'name' => $setName,
                    'order' => $index,
                ]);
            }
        }

        $collection->load(['preset', 'watermark', 'mediaSets' => function ($query) {
            $query->withCount('media')->orderBy('order', 'asc');
        }]);

        // Create notification
        $status = $collection->status?->value ?? $collection->status;
        $settings = $collection->settings ?? [];
        $coverPhoto = $settings['thumbnail'] ?? $settings['image'] ?? null;

        if ($status === 'active') {
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'collection_published',
                'Collection Published',
                "Collection '{$collection->name}' has been published successfully.",
                "Your collection '{$collection->name}' is now live and accessible.",
                null,
                MemoraFrontendUrls::collectionDetailPath($collection->uuid, $collection->project_uuid),
                $coverPhoto ? ['coverPhoto' => $coverPhoto] : null
            );

            // Log activity for collection published
            app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                action: 'collection_published',
                subject: $collection,
                description: "Collection '{$collection->name}' published.",
                properties: [
                    'collection_uuid' => $collection->uuid,
                    'collection_name' => $collection->name,
                    'project_uuid' => $collection->project_uuid,
                ],
                causer: $user
            );
        } else {
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'collection_created',
                'Collection Created',
                "Collection '{$collection->name}' has been created successfully.",
                "Your collection '{$collection->name}' is ready to use.",
                null,
                MemoraFrontendUrls::collectionDetailPath($collection->uuid, $collection->project_uuid),
                $coverPhoto ? ['coverPhoto' => $coverPhoto] : null
            );
        }

        $this->activityLogService->log(
            'created',
            $collection,
            "Created collection phase '{$collection->name}'",
            [
                'phase_type' => 'collection',
                'project_uuid' => $collection->project_uuid,
                'collection_uuid' => $collection->uuid,
            ],
            $user
        );

        Cache::forget("memora.dashboard.stats.{$user->uuid}");

        return $collection;
    }

    /**
     * Update a collection (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, validates collection belongs to that project. If null, finds any collection by ID.
     */
    public function update(?string $projectId, string $id, array $data): MemoraCollection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $collection = $this->find($projectId, $id);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }
        if (array_key_exists('watermarkId', $data)) {
            $updateData['watermark_uuid'] = $data['watermarkId'] ?: null;
        }
        if (isset($data['settings'])) {
            $updateData['settings'] = $data['settings'];
        }
        // Initialize settings once to preserve all changes
        if (! isset($updateData['settings'])) {
            $updateData['settings'] = $collection->settings ?? [];
        }
        $settings = &$updateData['settings'];

        // Handle thumbnail and image in settings (merge to preserve other settings)
        if (isset($data['thumbnail']) || isset($data['image'])) {
            if (isset($data['thumbnail'])) {
                $settings['thumbnail'] = $data['thumbnail'];
            }
            if (isset($data['image'])) {
                $settings['image'] = $data['image'];
            }
        }
        if (isset($data['eventDate'])) {
            // Store eventDate in settings
            if ($data['eventDate'] === null) {
                unset($settings['eventDate']);
            } else {
                $settings['eventDate'] = $data['eventDate'];
            }
        }

        // Handle display_settings in settings
        if (isset($data['display_settings'])) {
            $settings['display_settings'] = $data['display_settings'];
        }

        // Handle design settings (coverDesign, gridDesign, typographyDesign, colorDesign)
        if (isset($data['coverDesign']) || isset($data['gridDesign']) || isset($data['typographyDesign']) || isset($data['colorDesign'])) {

            // Initialize design structure if it doesn't exist
            if (! isset($settings['design'])) {
                $settings['design'] = [];
            }

            if (isset($data['coverDesign'])) {
                $settings['design']['cover'] = $data['coverDesign'];
                // Explicitly handle coverFocalPoint to preserve float values
                if (isset($data['coverDesign']['coverFocalPoint']) && is_array($data['coverDesign']['coverFocalPoint'])) {
                    $settings['design']['cover']['coverFocalPoint'] = [
                        'x' => isset($data['coverDesign']['coverFocalPoint']['x'])
                            ? (float) $data['coverDesign']['coverFocalPoint']['x']
                            : 50,
                        'y' => isset($data['coverDesign']['coverFocalPoint']['y'])
                            ? (float) $data['coverDesign']['coverFocalPoint']['y']
                            : 50,
                    ];
                }
            }

            if (isset($data['gridDesign'])) {
                $settings['design']['grid'] = $data['gridDesign'];
            }

            if (isset($data['typographyDesign'])) {
                $defaults = [
                    'fontFamily' => 'sans',
                    'fontStyle' => 'normal',
                ];
                $settings['design']['typography'] = array_merge($defaults, $data['typographyDesign']);
            }

            if (isset($data['colorDesign'])) {
                $settings['design']['color'] = $data['colorDesign'];
            }
        }

        // Ensure settings reference is maintained
        if (! isset($updateData['settings'])) {
            $updateData['settings'] = $collection->settings ?? [];
        }
        $settings = $updateData['settings'];

        // Extract all organized settings to flat keys to preserve existing values
        if (isset($settings['general']) && is_array($settings['general'])) {
            foreach ($settings['general'] as $key => $value) {
                if (! in_array($key, ['slideshowOptions'])) {
                    $settings[$key] = $value;
                }
            }
        }
        if (isset($settings['privacy']) && is_array($settings['privacy'])) {
            foreach ($settings['privacy'] as $key => $value) {
                if ($key !== 'collectionPassword') {
                    $settings[$key] = $value;
                }
            }
        }
        if (isset($settings['download']) && is_array($settings['download'])) {
            if (isset($settings['download']['highResolution'])) {
                $settings['highResolutionEnabled'] = $settings['download']['highResolution']['enabled'] ?? false;
                $settings['highResolutionSize'] = $settings['download']['highResolution']['size'] ?? '3600px';
            }
            if (isset($settings['download']['webSize'])) {
                $settings['webSizeEnabled'] = $settings['download']['webSize']['enabled'] ?? false;
                $settings['webSize'] = $settings['download']['webSize']['size'] ?? '1024px';
            }
            foreach ($settings['download'] as $key => $value) {
                if (! in_array($key, ['highResolution', 'webSize'])) {
                    $settings[$key] = $value;
                }
            }
        }
        if (isset($settings['favorite']) && is_array($settings['favorite'])) {
            $settings['favoriteEnabled'] = $settings['favorite']['enabled'] ?? true;
            $settings['favoritePhotos'] = $settings['favorite']['photos'] ?? true;
            $settings['favoriteNotes'] = $settings['favorite']['notes'] ?? true;
        }

        // Clear nested structures so organizeSettingsForStorage rebuilds them from flat keys
        unset($settings['general'], $settings['privacy'], $settings['download'], $settings['favorite']);

        // Apply updates from $data (flat keys take precedence)
        $generalSettings = [
            'url', 'tags', 'emailRegistration', 'galleryAssist', 'slideshow',
            'slideshowSpeed', 'slideshowAutoLoop',
            'socialSharing', 'language', 'autoExpiryDate', 'expiryDate', 'expiryDays',
        ];
        foreach ($generalSettings as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] === null) {
                    unset($settings[$key]);
                } else {
                    $settings[$key] = $data[$key];
                }
            }
        }

        $privacySettings = [
            'password', 'showOnHomepage', 'clientExclusiveAccess',
            'clientPrivatePassword', 'allowClientsMarkPrivate', 'clientOnlySets',
        ];
        foreach ($privacySettings as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] === null) {
                    unset($settings[$key]);
                } else {
                    $settings[$key] = $data[$key];
                }
            }
        }

        $updateData['settings'] = $settings;

        // Handle download settings
        $downloadSettings = [
            'photoDownload', 'highResolutionEnabled', 'webSizeEnabled', 'webSize',
            'downloadPin', 'downloadPinEnabled', 'limitDownloads', 'downloadLimit',
            'restrictToContacts', 'allowedDownloadEmails', 'downloadableSets',
        ];
        foreach ($downloadSettings as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] === null) {
                    unset($settings[$key]);
                } else {
                    $settings[$key] = $data[$key];
                }
            }
        }

        // Handle favorite settings
        $favoriteSettings = [
            'favoritePhotos', 'favoriteNotes', 'downloadEnabled', 'favoriteEnabled',
        ];
        foreach ($favoriteSettings as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] === null) {
                    unset($settings[$key]);
                } else {
                    $settings[$key] = $data[$key];
                }
            }
        }

        // Check if presetId is being changed
        $presetChanged = false;
        $newPreset = null;
        if (isset($data['presetId'])) {
            $newPresetUuid = $data['presetId'];
            if ($collection->preset_uuid !== $newPresetUuid) {
                $presetChanged = true;
                $updateData['preset_uuid'] = $newPresetUuid;

                // Load the new preset and apply all defaults
                if ($newPresetUuid) {
                    $newPreset = MemoraPreset::where('uuid', $newPresetUuid)
                        ->where('user_uuid', $user->uuid)
                        ->firstOrFail();

                    // Merge preset defaults with existing settings (preserve user changes)
                    $presetDefaults = $this->applyPresetDefaults($newPreset, $settings);
                    $settings = array_merge($presetDefaults, $settings);
                    $updateData['settings'] = $settings;

                    // Apply default watermark from preset if not explicitly set
                    if (! isset($data['watermarkId']) && $newPreset->default_watermark_uuid) {
                        $updateData['watermark_uuid'] = $newPreset->default_watermark_uuid;
                    }
                } else {
                    // Preset removed, but keep existing settings
                    $updateData['preset_uuid'] = null;
                }
            }
        }

        // Organize settings into structured format before saving
        $updateData['settings'] = $this->organizeSettingsForStorage($settings);

        // Check old status before update
        $oldStatus = $collection->status?->value ?? $collection->status;

        // Use fill() and save() to ensure Laravel detects JSON column changes
        $collection->fill($updateData);
        $collection->save();

        // Check new status after update
        $collection->refresh();
        $newStatus = $collection->status?->value ?? $collection->status;

        // Create notification if status changed to 'active' (published)
        $settings = $collection->settings ?? [];
        $coverPhoto = $settings['thumbnail'] ?? $settings['image'] ?? null;

        if ($oldStatus !== 'active' && $newStatus === 'active') {
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'collection_published',
                'Collection Published',
                "Collection '{$collection->name}' has been published successfully.",
                "Your collection '{$collection->name}' is now live and accessible to viewers.",
                null,
                MemoraFrontendUrls::collectionDetailPath($collection->uuid, $collection->project_uuid),
                $coverPhoto ? ['coverPhoto' => $coverPhoto] : null
            );

            // Log activity for collection published
            app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                action: 'collection_published',
                subject: $collection,
                description: "Collection '{$collection->name}' published.",
                properties: [
                    'collection_uuid' => $collection->uuid,
                    'collection_name' => $collection->name,
                    'project_uuid' => $collection->project_uuid,
                ],
                causer: $user
            );
        } elseif ($oldStatus === $newStatus) {
            // General update notification (when status hasn't changed)
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'collection_updated',
                'Collection Updated',
                "Collection '{$collection->name}' has been updated successfully.",
                "Your collection '{$collection->name}' settings have been saved.",
                null,
                MemoraFrontendUrls::collectionDetailPath($collection->uuid, $collection->project_uuid),
                $coverPhoto ? ['coverPhoto' => $coverPhoto] : null
            );
        }

        $this->activityLogService->log(
            'updated',
            $collection,
            "Updated collection phase '{$collection->name}'",
            [
                'phase_type' => 'collection',
                'project_uuid' => $collection->project_uuid,
                'collection_uuid' => $collection->uuid,
                'status_changed' => $oldStatus !== $newStatus,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
            $user
        );

        // Restore preset sets if preset changed and new preset has photo_sets
        if ($presetChanged && $newPreset && $newPreset->photo_sets && is_array($newPreset->photo_sets) && count($newPreset->photo_sets) > 0) {
            // Check if any set has media (excluding soft-deleted)
            $hasMedia = MemoraMedia::whereHas('mediaSet', function ($query) use ($collection) {
                $query->where('collection_uuid', $collection->uuid);
            })->whereNull('deleted_at')->exists();

            if ($hasMedia) {
                throw new \Exception('Cannot restore preset sets: Media exists in one or more sets. Please remove all media before changing the preset.');
            }

            // Delete existing sets
            MemoraMediaSet::where('collection_uuid', $collection->uuid)->delete();

            // Create sets from preset photo_sets
            foreach ($newPreset->photo_sets as $index => $setName) {
                MemoraMediaSet::create([
                    'user_uuid' => $user->uuid,
                    'collection_uuid' => $collection->uuid,
                    'project_uuid' => $collection->project_uuid,
                    'name' => $setName,
                    'order' => $index,
                ]);
            }
        }

        // Handle mediaSets updates
        if (isset($data['mediaSets']) && is_array($data['mediaSets'])) {
            $existingSetIds = MemoraMediaSet::where('collection_uuid', $collection->uuid)
                ->pluck('uuid')
                ->toArray();

            $providedSetIds = [];

            foreach ($data['mediaSets'] as $index => $setData) {
                if (isset($setData['id']) && ! empty($setData['id'])) {
                    // Try to find existing set by UUID
                    $setId = $setData['id'];
                    $set = MemoraMediaSet::where('uuid', $setId)
                        ->where('collection_uuid', $collection->uuid)
                        ->first();

                    if ($set) {
                        // Update existing set
                        $set->update([
                            'name' => $setData['name'] ?? '',
                            'description' => $setData['description'] ?? null,
                            'order' => $setData['order'] ?? $index,
                        ]);
                        $providedSetIds[] = $set->uuid;
                    } else {
                        // ID provided but set doesn't exist - create new one
                        $newSet = MemoraMediaSet::create([
                            'user_uuid' => $user->uuid,
                            'collection_uuid' => $collection->uuid,
                            'project_uuid' => $collection->project_uuid,
                            'name' => $setData['name'] ?? '',
                            'description' => $setData['description'] ?? null,
                            'order' => $setData['order'] ?? $index,
                        ]);
                        $providedSetIds[] = $newSet->uuid;
                    }
                } else {
                    // Create new set (no ID provided)
                    $newSet = MemoraMediaSet::create([
                        'user_uuid' => $user->uuid,
                        'collection_uuid' => $collection->uuid,
                        'project_uuid' => $collection->project_uuid,
                        'name' => $setData['name'] ?? '',
                        'description' => $setData['description'] ?? null,
                        'order' => $setData['order'] ?? $index,
                    ]);
                    $providedSetIds[] = $newSet->uuid;
                }
            }

            // Delete sets that are no longer in the provided list
            $setsToDelete = array_diff($existingSetIds, $providedSetIds);
            if (! empty($setsToDelete)) {
                MemoraMediaSet::where('collection_uuid', $collection->uuid)
                    ->whereIn('uuid', $setsToDelete)
                    ->delete();
            }
        }

        // Preserve existing sets when preset changes - do not delete/recreate sets

        Cache::forget("memora.dashboard.stats.{$user->uuid}");

        return $collection->fresh()->load(['preset', 'watermark', 'mediaSets' => function ($query) {
            $query->withCount('media')->orderBy('order', 'asc');
        }]);
    }

    /**
     * Get a collection (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, validates collection belongs to that project. If null, finds any collection by ID.
     */
    public function find(?string $projectId, string $id): MemoraCollection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraCollection::where('user_uuid', $user->uuid)
            ->where('uuid', $id)
            ->with(['preset', 'watermark', 'project', 'mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order', 'asc');
            }])
            ->with(['starredByUsers' => function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            }])
            // Add subqueries for media and set counts to avoid N+1 queries
            ->addSelect([
                'media_count' => MemoraMedia::query()->selectRaw('COALESCE(COUNT(*), 0)')
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->whereColumn('memora_media_sets.collection_uuid', 'memora_collections.uuid')
                    ->limit(1),
                'set_count' => MemoraMediaSet::query()->selectRaw('COALESCE(COUNT(*), 0)')
                    ->whereColumn('collection_uuid', 'memora_collections.uuid')
                    ->limit(1),
            ]);

        if ($projectId) {
            // Validate project exists and collection belongs to it
            MemoraProject::where('uuid', $projectId)
                ->where('user_uuid', $user->uuid)
                ->firstOrFail();
            $query->where('project_uuid', $projectId);
        }
        // If no projectId provided, find collection regardless of project association

        $collection = $query->firstOrFail();

        // Map the subquery results to the expected attribute names
        $collection->setAttribute('media_count', (int) ($collection->media_count ?? 0));
        $collection->setAttribute('set_count', (int) ($collection->set_count ?? 0));

        return $collection;
    }

    /**
     * Get storage used by collection (lightweight, for badge refresh).
     */
    public function getStorageUsed(?string $projectId, string $id): int
    {
        $collection = $this->find($projectId, $id);

        return app(\App\Services\Storage\UserStorageService::class)->getPhaseStorageUsed($collection->uuid, 'collection');
    }

    /**
     * Delete a collection (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, validates collection belongs to that project. If null, finds any collection by ID.
     */
    public function delete(?string $projectId, string $id): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $collection = $this->find($projectId, $id);
        $name = $collection->name;
        $deleted = $collection->delete();

        if ($deleted) {
            // Create notification
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'collection_deleted',
                'Collection Deleted',
                "Collection '{$name}' has been deleted.",
                "The collection '{$name}' has been permanently removed.",
                null,
                MemoraFrontendUrls::collectionListPath($collection->project_uuid)
            );

            $this->activityLogService->log(
                'deleted',
                null,
                "Deleted collection phase '{$name}'",
                [
                    'phase_type' => 'collection',
                    'collection_uuid' => $collection->uuid,
                    'project_uuid' => $collection->project_uuid,
                ],
                $user
            );
        }

        Cache::forget("memora.dashboard.stats.{$user->uuid}");

        return $deleted;
    }

    /**
     * Toggle star status for a collection
     *
     * @param  string|null  $projectId  Project UUID if collection is project-based
     * @param  string  $id  Collection UUID
     * @return array{starred: bool} Returns whether the collection is now starred
     */
    public function toggleStar(?string $projectId, string $id): array
    {
        $collection = $this->find($projectId, $id);
        $user = Auth::user();

        // Toggle the star relationship
        $user->starredCollections()->toggle($collection->uuid);

        // Check if it's now starred
        $isStarred = $user->starredCollections()->where('collection_uuid', $collection->uuid)->exists();

        return [
            'starred' => $isStarred,
        ];
    }

    /**
     * Duplicate a collection with all settings, media sets, and media
     *
     * @param  string|null  $projectId  Project UUID if collection is project-based
     * @param  string  $id  Collection UUID
     * @return MemoraCollection The duplicated collection
     */
    public function duplicate(?string $projectId, string $id): MemoraCollection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Load the original collection with all relationships
        $original = MemoraCollection::where('uuid', $id)
            ->where('user_uuid', $user->uuid)
            ->with([
                'mediaSets' => function ($query) {
                    $query->whereNull('deleted_at')
                        ->with(['media' => function ($q) {
                            $q->whereNull('deleted_at')->orderBy('order', 'asc');
                        }])->orderBy('order', 'asc');
                },
                'preset',
                'watermark',
            ])
            ->firstOrFail();

        if ($projectId) {
            // Validate project exists and collection belongs to it
            MemoraProject::where('uuid', $projectId)
                ->where('user_uuid', $user->uuid)
                ->firstOrFail();
            if ($original->project_uuid !== $projectId) {
                throw new \Exception('Collection does not belong to the specified project');
            }
        }

        // Create the duplicated collection
        $duplicated = MemoraCollection::create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $original->project_uuid,
            'preset_uuid' => $original->preset_uuid,
            'watermark_uuid' => $original->watermark_uuid,
            'name' => $original->name.' (Copy)',
            'description' => $original->description,
            'status' => 'draft',
            'color' => $original->color,
            'settings' => $original->settings,
        ]);

        // Duplicate media sets and their media
        $setMapping = [];
        foreach ($original->mediaSets as $originalSet) {
            $newSet = MemoraMediaSet::create([
                'user_uuid' => $user->uuid,
                'collection_uuid' => $duplicated->uuid,
                'project_uuid' => $originalSet->project_uuid,
                'name' => $originalSet->name,
                'description' => $originalSet->description,
                'order' => $originalSet->order,
                'selection_limit' => $originalSet->selection_limit,
            ]);
            $newSetUuid = $newSet->uuid;
            $setMapping[$originalSet->uuid] = $newSetUuid;

            // Duplicate media items (only non-deleted media)
            if ($originalSet->relationLoaded('media') && $originalSet->media) {
                foreach ($originalSet->media as $originalMedia) {
                    if (! $originalMedia->deleted_at) {
                        MemoraMedia::create([
                            'user_uuid' => $user->uuid,
                            'media_set_uuid' => $newSetUuid,
                            'user_file_uuid' => $originalMedia->user_file_uuid,
                            'original_file_uuid' => $originalMedia->original_file_uuid,
                            'watermark_uuid' => $originalMedia->watermark_uuid,
                            'order' => $originalMedia->order,
                            'is_private' => $originalMedia->is_private,
                            'is_featured' => false, // Reset featured status
                        ]);
                    }
                }
            }
        }

        // Reload the collection with relationships
        $duplicated->refresh();
        $duplicated->load(['preset', 'watermark', 'mediaSets' => function ($query) {
            $query->withCount('media')->orderBy('order', 'asc');
        }]);

        Cache::forget("memora.dashboard.stats.{$user->uuid}");

        return $duplicated;
    }
}
