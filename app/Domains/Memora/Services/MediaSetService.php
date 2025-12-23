<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraSelection;
use Illuminate\Support\Facades\Auth;

class MediaSetService
{
    /**
     * Create a media set in a selection
     */
    public function create(string $selectionId, array $data): MemoraMediaSet
    {
        $selection = MemoraSelection::findOrFail($selectionId);

        // Get the maximum order for sets in this selection
        $maxOrder = MemoraMediaSet::where('selection_uuid', $selectionId)
            ->max('order') ?? -1;

        return MemoraMediaSet::create([
            'user_uuid' => Auth::user()->uuid,
            'selection_uuid' => $selectionId,
            'project_uuid' => $selection->project_uuid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'order' => $maxOrder + 1,
        ]);
    }

    /**
     * Get all media sets for a selection
     */
    public function getBySelection(string $selectionId)
    {
        return MemoraMediaSet::where('selection_uuid', $selectionId)
            ->withCount('media')
            ->orderBy('order')
            ->get();
    }

    /**
     * Delete a media set and all media in it
     */
    public function delete(string $selectionId, string $id): bool
    {
        $set = $this->find($selectionId, $id);
        
        // Load media relationship if not already loaded
        if (!$set->relationLoaded('media')) {
            $set->load('media');
        }
        
        // Soft delete all media in this set
        // Loop through each media item to ensure soft deletes work correctly
        foreach ($set->media as $media) {
            $media->delete();
        }
        
        // Soft delete the set itself
        return $set->delete();
    }

    /**
     * Get a media set
     */
    public function find(string $selectionId, string $id): MemoraMediaSet
    {
        return MemoraMediaSet::where('selection_uuid', $selectionId)
            ->withCount('media')
            ->findOrFail($id);
    }

    /**
     * Reorder media sets
     */
    public function reorder(string $selectionId, array $setUuids): bool
    {
        foreach ($setUuids as $order => $setUuid) {
            MemoraMediaSet::where('selection_uuid', $selectionId)
                ->where('uuid', $setUuid)
                ->update(['order' => $order]);
        }

        return true;
    }

    /**
     * Update a media set
     */
    public function update(string $selectionId, string $id, array $data): MemoraMediaSet
    {
        $set = $this->find($selectionId, $id);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['order'])) {
            $updateData['order'] = $data['order'];
        }

        $set->update($updateData);

        return $set->fresh();
    }
}

