<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraSelection;
use Illuminate\Support\Facades\Auth;

class SelectionService
{
    /**
     * Create a selection (standalone or project-based based on project_uuid in data)
     */
    public function create(array $data): MemoraSelection
    {
        $projectUuid = $data['project_uuid'] ?? null;

        // If project_uuid is provided, validate it exists
        if ($projectUuid) {
            MemoraProject::findOrFail($projectUuid);
        }

        return MemoraSelection::create([
            'user_uuid' => Auth::user()->uuid,
            'project_uuid' => $projectUuid,
            'name' => $data['name'] ?? 'Selections',
            'status' => $data['status'] ?? 'active',
            'color' => $data['color'] ?? '#10B981',
            'cover_photo_url' => $data['cover_photo_url'] ?? null,
        ]);
    }

    /**
     * Get all selections (optionally filtered by project_uuid)
     */
    public function getAll(?string $projectUuid = null)
    {
        $query = MemoraSelection::where('user_uuid', Auth::user()->uuid)
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }]);

        if ($projectUuid) {
            $query->where('project_uuid', $projectUuid);
        } else {
            // If no project specified, get all (both standalone and project-based)
            // Or if you want only standalone: ->whereNull('project_uuid')
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Delete a selection
     */
    public function delete(string $id): bool
    {
        $selection = $this->find($id);
        return $selection->delete();
    }

    /**
     * Get a selection by ID (works for both standalone and project-based)
     */
    public function find(string $id): MemoraSelection
    {
        $selection = MemoraSelection::where('user_uuid', Auth::user()->uuid)
            ->where('uuid', $id)
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }])
            ->firstOrFail();

        // Calculate total media count across all sets
        $mediaCount = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('selection_uuid', $id);
        })->count();

        $selectedCount = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('selection_uuid', $id);
        })->where('is_selected', true)->count();

        $selection->setAttribute('media_count', $mediaCount);
        $selection->setAttribute('selected_count', $selectedCount);

        return $selection;
    }

    /**
     * Publish a selection (creative can only publish to active, not complete)
     */
    public function publish(string $id): MemoraSelection
    {
        $selection = MemoraSelection::where('user_uuid', Auth::user()->uuid)
            ->where('uuid', $id)
            ->firstOrFail();

        $selection->update([
            'status' => 'active',
        ]);

        // Return with calculated counts
        return $this->find($id);
    }

    /**
     * Update a selection
     */
    public function update(string $id, array $data): MemoraSelection
    {
        $selection = MemoraSelection::where('user_uuid', Auth::user()->uuid)
            ->where('uuid', $id)
            ->firstOrFail();

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['color'])) $updateData['color'] = $data['color'];
        if (isset($data['cover_photo_url'])) $updateData['cover_photo_url'] = $data['cover_photo_url'];

        $selection->update($updateData);

        // Return with calculated counts
        return $this->find($id);
    }

    /**
     * Complete a selection (only for guests)
     * Marks the provided media UUIDs as selected when completing
     */
    public function complete(string $id, array $mediaIds, ?string $completedByEmail = null): MemoraSelection
    {
        $selection = MemoraSelection::where('uuid', $id)
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }])
            ->firstOrFail();

        // Mark the provided media as selected
        MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('selection_uuid', $id);
        })
            ->whereIn('uuid', $mediaIds)
            ->update([
                'is_selected' => true,
                'selected_at' => now(),
            ]);

        $selection->update([
            'status' => 'completed',
            'selection_completed_at' => now(),
            'completed_by_email' => $completedByEmail,
            'auto_delete_date' => now()->addDays(30), // 30 days auto-delete
        ]);

        return $selection->fresh();
    }

    /**
     * Recover deleted media
     */
    public function recover(string $id, array $mediaIds): array
    {
        $selection = $this->find($id);

        $recovered = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('selection_uuid', $id);
        })
            ->whereIn('uuid', $mediaIds)
            ->withTrashed() // If soft deletes are enabled
            ->restore();

        return [
            'recoveredCount' => count($mediaIds),
        ];
    }

    /**
     * Get selected media
     */
    public function getSelectedMedia(string $id, ?string $setUuid = null)
    {
        $query = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('selection_uuid', $id);
        })
            ->where('is_selected', true)
            ->orderBy('order');

        if ($setUuid) {
            $query->where('media_set_uuid', $setUuid);
        }

        return $query->get();
    }

    /**
     * Get selected filenames
     */
    public function getSelectedFilenames(string $id): array
    {
        $filenames = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('selection_uuid', $id);
        })
            ->where('is_selected', true)
            ->orderBy('order')
            ->pluck('filename')
            ->toArray();

        return [
            'filenames' => $filenames,
            'count' => count($filenames),
        ];
    }
}
