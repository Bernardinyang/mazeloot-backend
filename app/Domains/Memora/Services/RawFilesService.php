<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraRawFiles;
use App\Domains\Memora\Resources\V1\RawFilesResource;
use App\Services\Notification\NotificationService;
use App\Services\Pagination\PaginationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RawFilesService
{
    protected PaginationService $paginationService;

    protected NotificationService $notificationService;

    public function __construct(
        PaginationService $paginationService,
        NotificationService $notificationService
    ) {
        $this->paginationService = $paginationService;
        $this->notificationService = $notificationService;
    }

    public function create(array $data): RawFilesResource
    {
        $projectUuid = $data['project_uuid'] ?? null;

        if ($projectUuid) {
            MemoraProject::query()->findOrFail($projectUuid);
        }

        $rawFilesData = [
            'user_uuid' => Auth::user()->uuid,
            'project_uuid' => $projectUuid,
            'name' => $data['name'],
            'description' => (isset($data['description']) && trim($data['description']) !== '') ? trim($data['description']) : null,
            'color' => $data['color'] ?? '#3B82F6',
        ];

        if (! empty($data['password'])) {
            $rawFilesData['password'] = $data['password'];
        }

        $rawFiles = MemoraRawFiles::query()->create($rawFilesData);

        return new RawFilesResource($this->findModel($rawFiles->uuid));
    }

    protected function findModel(string $id): MemoraRawFiles
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $rawFiles = MemoraRawFiles::query()->where('user_uuid', $user->uuid)
            ->where('uuid', $id)
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }])
            ->with(['starredByUsers' => function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            }])
            ->addSelect([
                'media_count' => MemoraMedia::query()->selectRaw('COUNT(*)')
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->whereColumn('memora_media_sets.raw_files_uuid', 'memora_raw_files.uuid')
                    ->limit(1),
            ])
            ->firstOrFail();

        $rawFiles->setAttribute('media_count', (int) ($rawFiles->media_count ?? 0));

        return $rawFiles;
    }

    public function getAll(
        ?string $projectUuid = null,
        ?string $search = null,
        ?string $sortBy = null,
        ?string $status = null,
        ?bool $starred = null,
        int $page = 1,
        int $perPage = 10
    ): array {
        $query = MemoraRawFiles::query()->where('user_uuid', Auth::user()->uuid)
            ->with(['project'])
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }])
            ->with(['starredByUsers' => function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            }])
            ->addSelect([
                'media_count' => MemoraMedia::query()->selectRaw('COALESCE(COUNT(*), 0)')
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->whereColumn('memora_media_sets.raw_files_uuid', 'memora_raw_files.uuid')
                    ->limit(1),
            ]);

        if ($projectUuid) {
            $query->where('project_uuid', $projectUuid);
        }

        if ($search && trim($search)) {
            $query->where('name', 'LIKE', '%'.trim($search).'%');
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($starred !== null) {
            if ($starred) {
                $query->whereHas('starredByUsers', function ($q) {
                    $q->where('user_uuid', Auth::user()->uuid);
                });
            } else {
                $query->whereDoesntHave('starredByUsers', function ($q) {
                    $q->where('user_uuid', Auth::user()->uuid);
                });
            }
        }

        if ($sortBy) {
            $this->applySorting($query, $sortBy);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $paginator = $this->paginationService->paginate($query, $perPage, $page);

        foreach ($paginator->items() as $rawFiles) {
            $rawFiles->setAttribute('media_count', (int) ($rawFiles->media_count ?? 0));
        }

        $data = RawFilesResource::collection($paginator->items());

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

    protected function applySorting(Builder $query, string $sortBy): void
    {
        $parts = explode('-', $sortBy);
        $field = $parts[0] ?? 'created_at';
        $direction = strtoupper($parts[1] ?? 'desc');

        if (! in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'DESC';
        }

        $fieldMap = [
            'created' => 'created_at',
            'name' => 'name',
            'status' => 'status',
        ];

        $dbField = $fieldMap[$field] ?? 'created_at';

        $query->orderBy($dbField, $direction);
    }

    public function publish(string $id): RawFilesResource
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $rawFiles = MemoraRawFiles::where('user_uuid', $user->uuid)
            ->where('uuid', $id)
            ->firstOrFail();

        $newStatus = match ($rawFiles->status->value) {
            'draft' => 'active',
            'active' => 'draft',
            'completed' => 'active',
            default => 'active',
        };

        if ($newStatus === 'active') {
            $allowedEmails = $rawFiles->allowed_emails ?? [];
            if (empty($allowedEmails) || ! is_array($allowedEmails) || count(array_filter($allowedEmails)) === 0) {
                throw new \Illuminate\Validation\ValidationException(
                    validator([], []),
                    ['allowed_emails' => ['At least one email address must be added to "Allowed Emails" before publishing the raw files phase.']]
                );
            }
        }

        $rawFiles->update(['status' => $newStatus]);

        $rawFiles->refresh();
        $rawFiles->load(['mediaSets' => function ($query) {
            $query->withCount('media')->orderBy('order');
        }]);
        $rawFiles->load(['starredByUsers' => function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid);
        }]);

        return new RawFilesResource($rawFiles);
    }

    public function update(string $id, array $data): RawFilesResource
    {
        $rawFiles = MemoraRawFiles::query()->where('user_uuid', Auth::user()->uuid)
            ->where('uuid', $id)
            ->firstOrFail();

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $desc = $data['description'];
            if ($desc === null || $desc === '') {
                $updateData['description'] = null;
            } else {
                $updateData['description'] = trim((string) $desc);
            }
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }
        if (isset($data['cover_photo_url'])) {
            $updateData['cover_photo_url'] = $data['cover_photo_url'];
        }

        if (array_key_exists('cover_focal_point', $data) || array_key_exists('coverFocalPoint', $data)) {
            $focalPoint = $data['cover_focal_point'] ?? $data['coverFocalPoint'] ?? null;
            if ($focalPoint !== null && is_array($focalPoint) && isset($focalPoint['x'], $focalPoint['y'])) {
                $updateData['cover_focal_point'] = [
                    'x' => (float) $focalPoint['x'],
                    'y' => (float) $focalPoint['y'],
                ];
            } else {
                $updateData['cover_focal_point'] = null;
            }
        }

        if (array_key_exists('password', $data)) {
            if (! empty($data['password'])) {
                $updateData['password'] = $data['password'];
            } else {
                $updateData['password'] = null;
            }
        }

        if (array_key_exists('allowed_emails', $data) || array_key_exists('allowedEmails', $data)) {
            $emails = $data['allowed_emails'] ?? $data['allowedEmails'] ?? [];
            $emails = is_array($emails) ? array_filter(array_map('trim', $emails)) : [];
            $updateData['allowed_emails'] = ! empty($emails) ? array_values($emails) : null;
        }

        // Handle settings updates (including download settings)
        $settings = $rawFiles->settings ?? [];
        $settingsUpdated = false;

        if (isset($data['settings'])) {
            $settings = array_merge($settings, $data['settings']);
            $settingsUpdated = true;
        }

        // Handle download settings
        if (isset($data['downloadPin']) || isset($data['downloadPinEnabled']) || isset($data['limitDownloads']) || isset($data['downloadLimit'])) {
            if (! isset($settings['download'])) {
                $settings['download'] = [];
            }
            if (array_key_exists('downloadPin', $data)) {
                $settings['download']['downloadPin'] = $data['downloadPin'];
            }
            if (array_key_exists('downloadPinEnabled', $data)) {
                $settings['download']['downloadPinEnabled'] = (bool) $data['downloadPinEnabled'];
            }
            if (array_key_exists('limitDownloads', $data)) {
                $settings['download']['limitDownloads'] = (bool) $data['limitDownloads'];
            }
            if (array_key_exists('downloadLimit', $data)) {
                $settings['download']['downloadLimit'] = $data['downloadLimit'] !== null ? (int) $data['downloadLimit'] : null;
            }
            $settingsUpdated = true;
        }

        if (isset($data['typographyDesign'])) {
            if (! isset($settings['design'])) {
                $settings['design'] = [];
            }
            $defaults = [
                'fontFamily' => 'sans',
                'fontStyle' => 'normal',
            ];
            $settings['design']['typography'] = array_merge($defaults, $data['typographyDesign']);
            $settingsUpdated = true;
        }

        if ($settingsUpdated) {
            $updateData['settings'] = $settings;
        }

        $newStatus = $updateData['status'] ?? $rawFiles->status->value;
        if ($newStatus === 'active') {
            $allowedEmails = $updateData['allowed_emails'] ?? $rawFiles->allowed_emails ?? [];
            if (empty($allowedEmails) || ! is_array($allowedEmails) || count(array_filter($allowedEmails)) === 0) {
                throw new \Illuminate\Validation\ValidationException(
                    validator([], []),
                    ['allowed_emails' => ['At least one email address must be added to "Allowed Emails" before publishing the raw files phase.']]
                );
            }
        }

        $rawFiles->update($updateData);

        return $this->find($id);
    }

    public function find(string $id): RawFilesResource
    {
        return new RawFilesResource($this->findModel($id));
    }

    public function setCoverPhotoFromMedia(string $rawFilesId, string $mediaUuid, ?array $focalPoint = null): RawFilesResource
    {
        $rawFiles = MemoraRawFiles::query()
            ->where('uuid', $rawFilesId)
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();

        $media = MemoraMedia::query()
            ->where('uuid', $mediaUuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->with('file')
            ->whereHas('mediaSet', function ($query) use ($rawFilesId) {
                $query->where('raw_files_uuid', $rawFilesId);
            })
            ->firstOrFail();

        $coverUrl = null;
        if ($media->file) {
            $file = $media->file;
            $fileType = $file->type?->value ?? $file->type;

            if ($fileType === 'video') {
                $coverUrl = $file->url ?? null;
            } else {
                $metadata = $file->metadata;
                if (is_string($metadata)) {
                    $metadata = json_decode($metadata, true);
                }

                if ($metadata && is_array($metadata) && isset($metadata['variants']) && is_array($metadata['variants'])) {
                    if (isset($metadata['variants']['original'])) {
                        $coverUrl = $metadata['variants']['original'];
                    } elseif (isset($metadata['variants']['large'])) {
                        $coverUrl = $metadata['variants']['large'];
                    } else {
                        $coverUrl = $file->url ?? null;
                    }
                } else {
                    $coverUrl = $file->url ?? null;
                }
            }
        }

        if (! $coverUrl) {
            throw new \RuntimeException('Media does not have a valid URL');
        }

        $updateData = [
            'cover_photo_url' => $coverUrl,
        ];

        if ($focalPoint !== null) {
            $updateData['cover_focal_point'] = $focalPoint;
        }

        $rawFiles->update($updateData);

        return $this->find($rawFilesId);
    }

    public function toggleStar(string $id): array
    {
        $rawFiles = $this->findModel($id);
        $user = Auth::user();

        $user->starredRawFiles()->toggle($rawFiles->uuid);

        $isStarred = $user->starredRawFiles()->where('raw_files_uuid', $rawFiles->uuid)->exists();

        return [
            'starred' => $isStarred,
        ];
    }

    public function duplicate(string $id): RawFilesResource
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $original = MemoraRawFiles::where('uuid', $id)
            ->where('user_uuid', $user->uuid)
            ->with([
                'mediaSets' => function ($query) {
                    $query->with(['media' => function ($q) {
                        $q->orderBy('order', 'asc');
                    }])->orderBy('order', 'asc');
                },
            ])
            ->firstOrFail();

        $duplicated = MemoraRawFiles::create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $original->project_uuid,
            'name' => $original->name.' (Copy)',
            'description' => $original->description,
            'status' => 'draft',
            'color' => $original->color,
            'password' => $original->password,
            'allowed_emails' => $original->allowed_emails,
            'settings' => $original->settings,
        ]);

        foreach ($original->mediaSets as $originalSet) {
            $newSet = MemoraMediaSet::create([
                'user_uuid' => $user->uuid,
                'raw_files_uuid' => $duplicated->uuid,
                'project_uuid' => $originalSet->project_uuid,
                'name' => $originalSet->name,
                'description' => $originalSet->description,
                'order' => $originalSet->order,
            ]);
            $newSet->refresh();
            $newSetUuid = $newSet->uuid;

            foreach ($originalSet->media as $originalMedia) {
                MemoraMedia::create([
                    'user_uuid' => $user->uuid,
                    'media_set_uuid' => $newSetUuid,
                    'user_file_uuid' => $originalMedia->user_file_uuid,
                    'original_file_uuid' => $originalMedia->original_file_uuid,
                    'watermark_uuid' => $originalMedia->watermark_uuid,
                    'order' => $originalMedia->order,
                    'is_private' => false,
                ]);
            }
        }

        return new RawFilesResource($this->findModel($duplicated->uuid));
    }

    public function delete(string $id): bool
    {
        $rawFiles = $this->findModel($id);

        if (! $rawFiles->relationLoaded('mediaSets')) {
            $rawFiles->load('mediaSets.media');
        }

        $mediaSets = $rawFiles->mediaSets;

        return DB::transaction(function () use ($mediaSets, $rawFiles) {
            $user = Auth::user();
            $name = $rawFiles->name;

            foreach ($mediaSets as $set) {
                if (! $set->relationLoaded('media')) {
                    $set->load('media');
                }

                foreach ($set->media as $media) {
                    $media->delete();
                }
                $set->delete();
            }

            $deleted = $rawFiles->delete();

            if ($deleted && $user) {
                $this->notificationService->create(
                    $user->uuid,
                    'memora',
                    'raw_files_deleted',
                    'Raw Files Deleted',
                    "Raw Files phase '{$name}' has been deleted.",
                    "The raw files phase '{$name}' has been permanently removed.",
                    '/memora/raw-files'
                );
            }

            return $deleted;
        });
    }
}
