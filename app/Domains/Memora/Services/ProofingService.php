<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProofing;
use App\Services\Upload\UploadService;

class ProofingService
{
    protected UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Create a proofing phase
     */
    public function create(string $projectId, array $data): MemoraProofing
    {
        return MemoraProofing::create([
            'project_id' => $projectId,
            'name' => $data['name'] ?? 'MemoraProofing',
            'max_revisions' => $data['maxRevisions'] ?? 3,
            'status' => 'active',
        ]);
    }

    /**
     * Upload a revision
     */
    public function uploadRevision(string $projectId, string $id, string $mediaId, int $revisionNumber, $file): array
    {
        $proofing = $this->find($projectId, $id);
        $media = MemoraMedia::where('phase_id', $id)
            ->where('phase', 'proofing')
            ->findOrFail($mediaId);

        // Upload the revision file via upload service
        $uploadResult = $this->uploadService->upload($file, [
            'purpose' => 'proofing_revision',
            'domain' => 'memora',
        ]);

        // Create revision record (assuming revisions table exists, or store in media)
        // For now, return the upload result formatted as revision
        return [
            'id' => $uploadResult->path, // Use path as ID for now
            'mediaId' => $mediaId,
            'revisionNumber' => $revisionNumber,
            'url' => $uploadResult->url,
            'thumbnail' => $uploadResult->url, // Simplified
            'createdAt' => now()->toIso8601String(),
        ];
    }

    /**
     * Get a proofing phase
     */
    public function find(string $projectId, string $id): MemoraProofing
    {
        $proofing = MemoraProofing::where('project_id', $projectId)->findOrFail($id);

        // Load counts
        $mediaCount = MemoraMedia::where('phase_id', $id)->where('phase', 'proofing')->count();
        $completedCount = MemoraMedia::where('phase_id', $id)
            ->where('phase', 'proofing')
            ->where('is_completed', true)
            ->count();

        $proofing->setAttribute('media_count', $mediaCount);
        $proofing->setAttribute('completed_count', $completedCount);
        $proofing->setAttribute('pending_count', $mediaCount - $completedCount);

        return $proofing;
    }

    /**
     * Complete proofing
     */
    public function complete(string $projectId, string $id): MemoraProofing
    {
        $proofing = $this->find($projectId, $id);

        $proofing->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $proofing->fresh();
    }

    /**
     * Update a proofing phase
     */
    public function update(string $projectId, string $id, array $data): MemoraProofing
    {
        $proofing = $this->find($projectId, $id);

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['maxRevisions'])) $updateData['max_revisions'] = $data['maxRevisions'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];

        $proofing->update($updateData);

        return $proofing->fresh();
    }

    /**
     * Move media to collection
     */
    public function moveToCollection(string $projectId, string $id, array $mediaIds, string $collectionId): array
    {
        $proofing = $this->find($projectId, $id);

        $moved = MemoraMedia::where('phase_id', $id)
            ->where('phase', 'proofing')
            ->whereIn('id', $mediaIds)
            ->update([
                'collection_id' => $collectionId,
                'phase' => 'collection',
            ]);

        return [
            'movedCount' => $moved,
            'collectionId' => $collectionId,
        ];
    }
}
