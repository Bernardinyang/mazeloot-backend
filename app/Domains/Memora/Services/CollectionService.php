<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\Collection;
use App\Domains\Memora\Models\Project;

class CollectionService
{
    /**
     * Get a collection
     */
    public function find(string $projectId, string $id): Collection
    {
        return Collection::where('project_id', $projectId)->findOrFail($id);
    }

    /**
     * List collections for a project
     */
    public function list(string $projectId)
    {
        return Collection::where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create a collection
     */
    public function create(string $projectId, array $data): Collection
    {
        return Collection::create([
            'project_id' => $projectId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
    }

    /**
     * Update a collection
     */
    public function update(string $projectId, string $id, array $data): Collection
    {
        $collection = $this->find($projectId, $id);

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];

        $collection->update($updateData);

        return $collection->fresh();
    }

    /**
     * Delete a collection
     */
    public function delete(string $projectId, string $id): bool
    {
        $collection = $this->find($projectId, $id);
        return $collection->delete();
    }
}
