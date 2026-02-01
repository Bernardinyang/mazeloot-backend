<?php

namespace App\Domains\Memora\Resources\V1;

use App\Services\Subscription\TierService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class MediaSetResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = Auth::user();
        $features = $user ? app(TierService::class)->getFeatures($user) : [];

        $hasSelection = in_array('selection', $features, true);
        $hasProofing = in_array('proofing', $features, true);
        $hasCollection = in_array('collection', $features, true);
        $hasRawFiles = in_array('raw_files', $features, true);

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'count' => $this->whenCounted('media', $this->media_count ?? 0),
            'approvedCount' => $this->when(isset($this->approved_count), $this->approved_count ?? 0),
            'order' => $this->order,
            'selectionLimit' => $this->selection_limit,
            'selectionUuid' => $hasSelection ? $this->selection_uuid : null,
            'proofUuid' => $hasProofing ? $this->proof_uuid : null,
            'collectionUuid' => $hasCollection ? $this->collection_uuid : null,
            'rawFileUuid' => $hasRawFiles ? $this->raw_file_uuid : null,
            'media' => $this->whenLoaded('media', function () {
                return MediaResource::collection($this->media);
            }, []),
            'selection' => $hasSelection && $this->relationLoaded('selection') && $this->selection
                ? new SelectionResource($this->selection)
                : null,
            'proofing' => $hasProofing && $this->relationLoaded('proofing') && $this->proofing
                ? new ProofingResource($this->proofing)
                : null,
            'collection' => $hasCollection && $this->relationLoaded('collection') && $this->collection
                ? new CollectionResource($this->collection)
                : null,
            'project' => $this->whenLoaded('project', function () {
                return new ProjectResource($this->project);
            }, null),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
