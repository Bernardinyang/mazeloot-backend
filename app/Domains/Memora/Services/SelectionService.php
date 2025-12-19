<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraSelection;

class SelectionService
{
    /**
     * Create a selection phase
     */
    public function create(string $projectId, array $data): MemoraSelection
    {
        $project = MemoraProject::findOrFail($projectId);

        return MemoraSelection::create([
            'project_id' => $projectId,
            'name' => $data['name'] ?? 'Selections',
            'status' => 'active',
        ]);
    }

    /**
     * Complete selection
     */
    public function complete(string $projectId, string $id): MemoraSelection
    {
        $selection = $this->find($projectId, $id);

        $selection->update([
            'status' => 'completed',
            'selection_completed_at' => now(),
            'auto_delete_date' => now()->addDays(30), // 30 days auto-delete
        ]);

        return $selection->fresh();
    }

    /**
     * Get a selection
     */
    public function find(string $projectId, string $id): MemoraSelection
    {
        $selection = MemoraSelection::where('project_id', $projectId)
            ->findOrFail($id);

        // Calculate counts
        $mediaCount = MemoraMedia::where('phase_id', $id)->where('phase', 'selection')->count();
        $selectedCount = MemoraMedia::where('phase_id', $id)
            ->where('phase', 'selection')
            ->where('is_selected', true)
            ->count();

        $selection->setAttribute('media_count', $mediaCount);
        $selection->setAttribute('selected_count', $selectedCount);

        return $selection;
    }

    /**
     * Update a selection
     */
    public function update(string $projectId, string $id, array $data): MemoraSelection
    {
        $selection = $this->find($projectId, $id);

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];

        $selection->update($updateData);

        return $selection->fresh();
    }

    /**
     * Recover deleted media
     */
    public function recover(string $projectId, string $id, array $mediaIds): array
    {
        $selection = $this->find($projectId, $id);

        $recovered = MemoraMedia::where('phase_id', $id)
            ->whereIn('id', $mediaIds)
            ->where('phase', 'selection')
            ->withTrashed() // If soft deletes are enabled
            ->restore();

        return [
            'recoveredCount' => count($mediaIds),
        ];
    }

    /**
     * Get selected media
     */
    public function getSelectedMedia(string $projectId, string $id, ?string $setId = null)
    {
        $query = MemoraMedia::where('phase_id', $id)
            ->where('phase', 'selection')
            ->where('is_selected', true)
            ->orderBy('order');

        if ($setId) {
            $query->where('set_id', $setId);
        }

        return $query->get();
    }

    /**
     * Get selected filenames
     */
    public function getSelectedFilenames(string $projectId, string $id): array
    {
        $filenames = MemoraMedia::where('phase_id', $id)
            ->where('phase', 'selection')
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
