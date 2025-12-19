<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreProofingRequest;
use App\Domains\Memora\Requests\V1\UpdateProofingRequest;
use App\Domains\Memora\Resources\V1\ProofingResource;
use App\Domains\Memora\Services\ProofingService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProofingController extends Controller
{
    protected ProofingService $proofingService;

    public function __construct(ProofingService $proofingService)
    {
        $this->proofingService = $proofingService;
    }

    public function show(string $projectId, string $id): JsonResponse
    {
        $proofing = $this->proofingService->find($projectId, $id);
        return ApiResponse::success(new ProofingResource($proofing));
    }

    public function store(StoreProofingRequest $request, string $projectId): JsonResponse
    {
        $proofing = $this->proofingService->create($projectId, $request->validated());
        return ApiResponse::success(new ProofingResource($proofing), 201);
    }

    public function update(UpdateProofingRequest $request, string $projectId, string $id): JsonResponse
    {
        $proofing = $this->proofingService->update($projectId, $id, $request->validated());
        return ApiResponse::success(new ProofingResource($proofing));
    }

    public function uploadRevision(Request $request, string $projectId, string $id): JsonResponse
    {
        $request->validate([
            'mediaId' => 'required|uuid',
            'file' => 'required|file',
            'revisionNumber' => 'required|integer',
        ]);

        $revision = $this->proofingService->uploadRevision(
            $projectId,
            $id,
            $request->input('mediaId'),
            $request->input('revisionNumber'),
            $request->file('file')
        );

        return ApiResponse::success($revision, 201);
    }

    public function complete(string $projectId, string $id): JsonResponse
    {
        $proofing = $this->proofingService->complete($projectId, $id);
        return ApiResponse::success([
            'id' => $proofing->id,
            'status' => $proofing->status,
            'completedAt' => $proofing->completed_at?->toIso8601String(),
        ]);
    }

    public function moveToCollection(Request $request, string $projectId, string $id): JsonResponse
    {
        $request->validate([
            'mediaIds' => 'required|array',
            'mediaIds.*' => 'uuid',
            'collectionId' => 'required|uuid',
        ]);

        $result = $this->proofingService->moveToCollection(
            $projectId,
            $id,
            $request->input('mediaIds'),
            $request->input('collectionId')
        );

        return ApiResponse::success($result);
    }
}

