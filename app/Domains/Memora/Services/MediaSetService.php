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

        $setData = [
            'user_uuid' => Auth::user()->uuid,
            'selection_uuid' => $selectionId,
            'project_uuid' => $selection->project_uuid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'order' => $maxOrder + 1,
        ];

        // Handle selection_limit (support both snake_case and camelCase)
        // Use array_key_exists to allow null values to be set
        if (array_key_exists('selection_limit', $data) || array_key_exists('selectionLimit', $data)) {
            $limit = $data['selection_limit'] ?? $data['selectionLimit'] ?? null;
            // Explicitly set to null if null, empty string, or 0; otherwise cast to int
            if ($limit === null || $limit === '' || $limit === 0) {
                $setData['selection_limit'] = null;
            } else {
                $setData['selection_limit'] = (int) $limit;
            }
        }

        return MemoraMediaSet::create($setData);
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
        // Handle selection_limit update (support both snake_case and camelCase)
        if (array_key_exists('selection_limit', $data) || array_key_exists('selectionLimit', $data)) {
            $limit = $data['selection_limit'] ?? $data['selectionLimit'] ?? null;
            // Explicitly set to null if null, empty string, or 0; otherwise cast to int
            if ($limit === null || $limit === '' || $limit === 0) {
                $updateData['selection_limit'] = null;
            } else {
                $updateData['selection_limit'] = (int) $limit;
            }
        }

        $set->update($updateData);

        return $set->fresh();
    }
}

