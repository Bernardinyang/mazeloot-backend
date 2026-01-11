<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraPreset;
use App\Domains\Memora\Models\MemoraProject;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PresetService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all presets for the authenticated user
     */
    public function getByUser(?string $search = null, ?string $sortBy = 'created_at', ?string $sortOrder = 'desc'): Collection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraPreset::where('user_uuid', $user->uuid)
            ->with('defaultWatermark')
            ->select('memora_presets.*')
            ->addSelect([
                'usage_count' => DB::raw('(
                    SELECT COALESCE(COUNT(*), 0)
                    FROM memora_collections
                    WHERE memora_collections.preset_uuid = memora_presets.uuid
                ) + (
                    SELECT COALESCE(COUNT(*), 0)
                    FROM memora_projects
                    WHERE memora_projects.preset_uuid = memora_presets.uuid
                )'),
            ]);

        if ($search && trim($search)) {
            $searchTerm = '%'.trim($search).'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                    ->orWhere('description', 'LIKE', $searchTerm)
                    ->orWhere('category', 'LIKE', $searchTerm)
                    ->orWhere('collection_tags', 'LIKE', $searchTerm);
            });
        }

        // Validate sort fields
        $allowedSorts = ['name', 'created_at', 'updated_at', 'usage_count'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';

        // If no explicit sort, use order field first, then fallback to created_at
        if ($sortBy === 'created_at' && $sortOrder === 'desc') {
            $query->orderBy('order', 'asc')->orderBy('created_at', 'desc');
        } elseif ($sortBy === 'usage_count') {
            $query->orderByRaw("usage_count {$sortOrder}");
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->get();
    }

    /**
     * Reorder presets
     */
    public function reorder(array $presetIds): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Verify all presets belong to the user
        $presets = MemoraPreset::where('user_uuid', $user->uuid)
            ->whereIn('uuid', $presetIds)
            ->get();

        if ($presets->count() !== count($presetIds)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], [])->errors()->add('presets', 'Some presets do not belong to you.')
            );
        }

        // Update order for each preset
        foreach ($presetIds as $index => $presetId) {
            MemoraPreset::where('uuid', $presetId)
                ->where('user_uuid', $user->uuid)
                ->update(['order' => $index + 1]);
        }

        return true;
    }

    /**
     * Get single preset by ID (user-scoped)
     */
    public function getById(string $id): MemoraPreset
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        return MemoraPreset::where('user_uuid', $user->uuid)
            ->with('defaultWatermark')
            ->findOrFail($id);
    }

    /**
     * Get single preset by name (user-scoped, URL-friendly)
     */
    public function getByName(string $name): ?MemoraPreset
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Normalize the name for comparison
        $normalizedName = strtolower(str_replace([' ', '_'], '-', $name));

        // Get all user presets and find by normalized name
        $presets = MemoraPreset::where('user_uuid', $user->uuid)
            ->with('defaultWatermark')
            ->get();

        foreach ($presets as $preset) {
            $presetNormalizedName = strtolower(str_replace([' ', '_'], '-', $preset->name));
            if ($presetNormalizedName === $normalizedName) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Map frontend data to backend database fields
     */
    protected function mapDataToDatabase(array $data): array
    {
        $dbData = [];

        if (isset($data['name'])) {
            $dbData['name'] = $data['name'];
        }

        if (isset($data['description'])) {
            $dbData['description'] = $data['description'];
        }
        if (isset($data['category'])) {
            $dbData['category'] = $data['category'];
        }

        if (isset($data['isSelected'])) {
            $dbData['is_selected'] = $data['isSelected'];
        }
        if (isset($data['collectionTags'])) {
            $dbData['collection_tags'] = $data['collectionTags'];
        }
        if (isset($data['photoSets'])) {
            $dbData['photo_sets'] = $data['photoSets'];
        }
        if (isset($data['defaultWatermarkId'])) {
            $dbData['default_watermark_uuid'] = $data['defaultWatermarkId'];
        }
        if (array_key_exists('emailRegistration', $data)) {
            $dbData['email_registration'] = $data['emailRegistration'];
        }
        if (array_key_exists('galleryAssist', $data)) {
            $dbData['gallery_assist'] = $data['galleryAssist'];
        }
        if (array_key_exists('slideshow', $data)) {
            $dbData['slideshow'] = $data['slideshow'];
        }
        if (isset($data['slideshowSpeed'])) {
            $dbData['slideshow_speed'] = $data['slideshowSpeed'];
        }
        if (array_key_exists('slideshowAutoLoop', $data)) {
            $dbData['slideshow_auto_loop'] = $data['slideshowAutoLoop'];
        }
        if (array_key_exists('socialSharing', $data)) {
            $dbData['social_sharing'] = $data['socialSharing'];
        }
        if (isset($data['language'])) {
            $dbData['language'] = $data['language'];
        }

        // Design fields (excluding cover style/focal point)
        if (isset($data['design'])) {
            $design = $data['design'];
            if (isset($design['fontFamily'])) {
                $dbData['design_font_family'] = $design['fontFamily'];
            }
            if (isset($design['fontStyle'])) {
                $dbData['design_font_style'] = $design['fontStyle'];
            }
            if (isset($design['colorPalette'])) {
                $dbData['design_color_palette'] = $design['colorPalette'];
            }
            if (isset($design['gridStyle'])) {
                $dbData['design_grid_style'] = $design['gridStyle'];
            }
            if (isset($design['gridColumns'])) {
                $dbData['design_grid_columns'] = $design['gridColumns'];
            }
            if (isset($design['thumbnailOrientation'])) {
                $dbData['design_thumbnail_orientation'] = $design['thumbnailOrientation'];
            }
            if (isset($design['gridSpacing'])) {
                $dbData['design_grid_spacing'] = $design['gridSpacing'];
            }
            if (isset($design['tabStyle'])) {
                $dbData['design_tab_style'] = $design['tabStyle'];
            }
            if (isset($design['joyCover'])) {
                $joyCover = $design['joyCover'];
                if (isset($joyCover['title'])) {
                    $dbData['design_joy_cover_title'] = $joyCover['title'];
                }
                if (isset($joyCover['avatar'])) {
                    $dbData['design_joy_cover_avatar'] = $joyCover['avatar'];
                }
                if (array_key_exists('showDate', $joyCover)) {
                    $dbData['design_joy_cover_show_date'] = $joyCover['showDate'];
                }
                if (array_key_exists('showName', $joyCover)) {
                    $dbData['design_joy_cover_show_name'] = $joyCover['showName'];
                }
                if (isset($joyCover['buttonText'])) {
                    $dbData['design_joy_cover_button_text'] = $joyCover['buttonText'];
                }
                if (array_key_exists('showButton', $joyCover)) {
                    $dbData['design_joy_cover_show_button'] = $joyCover['showButton'];
                }
                if (isset($joyCover['backgroundPattern'])) {
                    $dbData['design_joy_cover_background_pattern'] = $joyCover['backgroundPattern'];
                }
            }
        }

        // Privacy fields
        if (isset($data['privacy'])) {
            $privacy = $data['privacy'];
            if (array_key_exists('collectionPassword', $privacy)) {
                $dbData['privacy_collection_password'] = $privacy['collectionPassword'];
            }
            if (array_key_exists('showOnHomepage', $privacy)) {
                $dbData['privacy_show_on_homepage'] = $privacy['showOnHomepage'];
            }
            if (array_key_exists('clientExclusiveAccess', $privacy)) {
                $dbData['privacy_client_exclusive_access'] = $privacy['clientExclusiveAccess'];
            }
            if (array_key_exists('allowClientsMarkPrivate', $privacy)) {
                $dbData['privacy_allow_clients_mark_private'] = $privacy['allowClientsMarkPrivate'];
            }
            if (isset($privacy['clientOnlySets'])) {
                $dbData['privacy_client_only_sets'] = $privacy['clientOnlySets'];
            }
        }

        // Download fields
        if (isset($data['download'])) {
            $download = $data['download'];
            if (array_key_exists('photoDownload', $download)) {
                $dbData['download_photo_download'] = $download['photoDownload'];
            }
            if (isset($download['highResolution'])) {
                $hr = $download['highResolution'];
                if (array_key_exists('enabled', $hr)) {
                    $dbData['download_high_resolution_enabled'] = $hr['enabled'];
                }
                if (isset($hr['size'])) {
                    $dbData['download_high_resolution_size'] = $hr['size'];
                }
            }
            if (isset($download['webSize'])) {
                $ws = $download['webSize'];
                if (array_key_exists('enabled', $ws)) {
                    $dbData['download_web_size_enabled'] = $ws['enabled'];
                }
                if (isset($ws['size'])) {
                    $dbData['download_web_size'] = $ws['size'];
                }
            }
            if (array_key_exists('videoDownload', $download)) {
                $dbData['download_video_download'] = $download['videoDownload'];
            }
            if (array_key_exists('downloadPin', $download)) {
                $dbData['download_download_pin'] = $download['downloadPin'];
            }
            if (array_key_exists('downloadPinEnabled', $download)) {
                $dbData['download_download_pin_enabled'] = $download['downloadPinEnabled'];
            }
            if (array_key_exists('limitDownloads', $download)) {
                $dbData['download_limit_downloads'] = $download['limitDownloads'];
            }
            if (isset($download['downloadLimit'])) {
                $dbData['download_download_limit'] = $download['downloadLimit'];
            }
            if (array_key_exists('restrictToContacts', $download)) {
                $dbData['download_restrict_to_contacts'] = $download['restrictToContacts'];
            }
            if (isset($download['downloadableSets'])) {
                $dbData['download_downloadable_sets'] = $download['downloadableSets'];
            }
        }

        // Favorite fields
        if (isset($data['favorite'])) {
            $favorite = $data['favorite'];
            if (array_key_exists('enabled', $favorite)) {
                $dbData['favorite_favorite_enabled'] = $favorite['enabled'];
            }
            if (array_key_exists('photos', $favorite)) {
                $dbData['favorite_favorite_photos'] = $favorite['photos'];
            }
            if (array_key_exists('notes', $favorite)) {
                $dbData['favorite_favorite_notes'] = $favorite['notes'];
            }
        }

        return $dbData;
    }

    /**
     * Create a preset
     */
    public function create(array $data): MemoraPreset
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Ensure name is present (should be validated by request, but be defensive)
        if (empty($data['name'])) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], [])->errors()->add('name', 'Preset name is required.')
            );
        }

        $dbData = $this->mapDataToDatabase($data);
        $dbData['user_uuid'] = $user->uuid;

        $preset = MemoraPreset::create($dbData);
        $preset->load('defaultWatermark');

        // Create notification
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'preset_created',
            'Preset Created',
            "Preset '{$preset->name}' has been created successfully.",
            "Your new preset '{$preset->name}' is now available to use.",
            '/memora/settings/preset'
        );

        return $preset;
    }

    /**
     * Update a preset
     */
    public function update(string $id, array $data): MemoraPreset
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $preset = MemoraPreset::where('user_uuid', $user->uuid)
            ->findOrFail($id);

        $dbData = $this->mapDataToDatabase($data);

        // Only update if there's data to update
        if (! empty($dbData)) {
            $preset->update($dbData);
            $preset->refresh();
        }

        $preset->load('defaultWatermark');

        // Create notification
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'preset_updated',
            'Preset Updated',
            "Preset '{$preset->name}' has been updated successfully.",
            "Your preset '{$preset->name}' settings have been saved.",
            '/memora/settings/preset'
        );

        return $preset;
    }

    /**
     * Delete a preset
     */
    public function delete(string $id): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $preset = MemoraPreset::where('user_uuid', $user->uuid)
            ->findOrFail($id);

        $name = $preset->name;
        $deleted = $preset->delete();

        if ($deleted) {
            // Create notification
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'preset_deleted',
                'Preset Deleted',
                "Preset '{$name}' has been deleted.",
                "The preset '{$name}' has been permanently removed.",
                '/memora/settings/preset'
            );
        }

        return $deleted;
    }

    /**
     * Duplicate a preset
     */
    public function duplicate(string $id): MemoraPreset
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $original = MemoraPreset::where('user_uuid', $user->uuid)
            ->with('defaultWatermark')
            ->findOrFail($id);

        $duplicateData = $original->toArray();
        unset($duplicateData['uuid'], $duplicateData['id'], $duplicateData['created_at'], $duplicateData['updated_at']);
        $duplicateData['name'] = $original->name.' (Copy)';
        $duplicateData['is_selected'] = false;
        $duplicateData['user_uuid'] = $user->uuid;

        $duplicate = MemoraPreset::create($duplicateData);
        $duplicate->load('defaultWatermark');

        return $duplicate;
    }

    /**
     * Apply preset settings to a collection
     */
    public function applyToCollection(string $presetId, string $collectionId): MemoraCollection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $preset = MemoraPreset::where('user_uuid', $user->uuid)
            ->findOrFail($presetId);

        $collection = MemoraCollection::where('user_uuid', $user->uuid)
            ->findOrFail($collectionId);

        // Get existing settings to preserve eventDate and other important data
        $existingSettings = $collection->settings ?? [];

        // Use CollectionService to apply preset defaults
        $collectionService = app(\App\Domains\Memora\Services\CollectionService::class);
        $presetSettings = $collectionService->applyPresetDefaults($preset, $existingSettings);

        // Flatten nested settings to match collection structure
        $settings = array_merge($existingSettings, [
            // General settings
            'emailRegistration' => $presetSettings['emailRegistration'] ?? false,
            'galleryAssist' => $presetSettings['galleryAssist'] ?? false,
            'slideshow' => $presetSettings['slideshow'] ?? true,
            'slideshowSpeed' => $presetSettings['slideshowSpeed'] ?? 'regular',
            'slideshowAutoLoop' => $presetSettings['slideshowAutoLoop'] ?? true,
            'socialSharing' => $presetSettings['socialSharing'] ?? true,
            'language' => $presetSettings['language'] ?? 'en',
            'tags' => $presetSettings['collectionTags'] ?? null,
            // Privacy settings (flatten from privacy array)
            'password' => $presetSettings['privacy']['collectionPassword'] ? '' : null, // Preset stores boolean, collection needs string (empty string means enabled)
            'showOnHomepage' => $presetSettings['privacy']['showOnHomepage'] ?? false,
            'clientExclusiveAccess' => $presetSettings['privacy']['clientExclusiveAccess'] ?? false,
            'allowClientsMarkPrivate' => $presetSettings['privacy']['allowClientsMarkPrivate'] ?? false,
            'clientOnlySets' => $presetSettings['privacy']['clientOnlySets'] ?? null,
            // Download settings (flatten from download array)
            'photoDownload' => $presetSettings['download']['photoDownload'] ?? false,
            'highResolutionEnabled' => $presetSettings['download']['highResolution']['enabled'] ?? false,
            'webSizeEnabled' => $presetSettings['download']['webSize']['enabled'] ?? false,
            'webSize' => $presetSettings['download']['webSize']['size'] ?? null,
            'downloadPin' => $presetSettings['download']['downloadPin'] ?? null,
            'downloadPinEnabled' => $presetSettings['download']['downloadPinEnabled'] ?? false,
            'limitDownloads' => $presetSettings['download']['limitDownloads'] ?? false,
            'downloadLimit' => $presetSettings['download']['downloadLimit'] ?? null,
            'restrictToContacts' => $presetSettings['download']['restrictToContacts'] ?? false,
            'allowedDownloadEmails' => null, // Presets don't store email lists
            'downloadableSets' => $presetSettings['download']['downloadableSets'] ?? null,
            // Favorite settings (flatten from favorite array)
            'favoritePhotos' => $presetSettings['favorite']['photos'] ?? false,
            'favoriteNotes' => $presetSettings['favorite']['notes'] ?? false,
            'downloadEnabled' => $presetSettings['favorite']['enabled'] ?? true,
            'favoriteEnabled' => $presetSettings['favorite']['enabled'] ?? true,
            // Design settings
            'design' => $presetSettings['design'] ?? [],
            'typographyDesign' => [
                'fontFamily' => $presetSettings['design']['fontFamily'] ?? 'sans',
                'fontStyle' => $presetSettings['design']['fontStyle'] ?? 'normal',
            ],
            'colorDesign' => [
                'colorPalette' => $presetSettings['design']['colorPalette'] ?? 'light',
            ],
            'gridDesign' => [
                'gridStyle' => $presetSettings['design']['gridStyle'] ?? 'classic',
                'gridColumns' => $presetSettings['design']['gridColumns'] ?? 3,
                'thumbnailOrientation' => $presetSettings['design']['thumbnailOrientation'] ?? 'medium',
                'gridSpacing' => $presetSettings['design']['gridSpacing'] ?? 'normal',
                'tabStyle' => $presetSettings['design']['tabStyle'] ?? 'icon-text',
            ],
        ]);

        $updateData = [
            'preset_uuid' => $preset->uuid,
            'settings' => $settings,
        ];

        // Apply default watermark from preset if collection doesn't have one
        if (! $collection->watermark_uuid && $preset->default_watermark_uuid) {
            $updateData['watermark_uuid'] = $preset->default_watermark_uuid;
        }

        $collection->update($updateData);

        return $collection->fresh();
    }

    /**
     * Get preset usage count (how many collections/projects use this preset)
     */
    public function getUsageCount(string $id): int
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $preset = MemoraPreset::where('user_uuid', $user->uuid)
            ->findOrFail($id);

        $collectionsCount = MemoraCollection::where('preset_uuid', $preset->uuid)->count();
        $projectsCount = MemoraProject::where('preset_uuid', $preset->uuid)->count();

        return $collectionsCount + $projectsCount;
    }

    /**
     * Set a preset as default (unset all others for the user)
     */
    public function setAsDefault(string $id): MemoraPreset
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $preset = MemoraPreset::where('user_uuid', $user->uuid)
            ->findOrFail($id);

        // Unset all other presets for this user
        MemoraPreset::where('user_uuid', $user->uuid)
            ->where('uuid', '!=', $preset->uuid)
            ->update(['is_selected' => false]);

        // Set this preset as default
        $preset->update(['is_selected' => true]);
        $preset->refresh();
        $preset->load('defaultWatermark');

        return $preset;
    }

    /**
     * Get the default preset for the user
     */
    public function getDefault(): ?MemoraPreset
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        return MemoraPreset::where('user_uuid', $user->uuid)
            ->where('is_selected', true)
            ->with('defaultWatermark')
            ->first();
    }

    /**
     * Calculate preset completeness score (0-100)
     */
    public function getCompletenessScore(MemoraPreset $preset): int
    {
        $totalFields = 0;
        $filledFields = 0;

        // General fields
        $generalFields = ['name', 'description', 'default_watermark_uuid', 'language'];
        foreach ($generalFields as $field) {
            $totalFields++;
            if (! empty($preset->$field)) {
                $filledFields++;
            }
        }

        // Design fields
        $designFields = [
            'design_font_family', 'design_color_palette', 'design_grid_style',
            'design_grid_columns', 'design_thumbnail_orientation',
        ];
        foreach ($designFields as $field) {
            $totalFields++;
            if (! empty($preset->$field)) {
                $filledFields++;
            }
        }

        // Privacy fields
        $privacyFields = ['privacy_collection_password', 'privacy_show_on_homepage'];
        foreach ($privacyFields as $field) {
            $totalFields++;
            if ($preset->$field !== null) {
                $filledFields++;
            }
        }

        // Download fields
        $downloadFields = [
            'download_photo_download', 'download_high_resolution_enabled',
            'download_web_size_enabled',
        ];
        foreach ($downloadFields as $field) {
            $totalFields++;
            if ($preset->$field !== null) {
                $filledFields++;
            }
        }

        // Favorite fields
        $favoriteFields = ['favorite_favorite_enabled'];
        foreach ($favoriteFields as $field) {
            $totalFields++;
            if ($preset->$field !== null) {
                $filledFields++;
            }
        }

        return $totalFields > 0 ? (int) round(($filledFields / $totalFields) * 100) : 0;
    }
}
