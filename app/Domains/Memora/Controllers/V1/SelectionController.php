<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreSelectionRequest;
use App\Domains\Memora\Requests\V1\UpdateSelectionRequest;
use App\Domains\Memora\Requests\V1\RecoverMediaRequest;
use App\Domains\Memora\Resources\V1\SelectionResource;
use App\Domains\Memora\Resources\V1\MediaResource;
use App\Domains\Memora\Services\SelectionService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelectionController extends Controller
{
    protected SelectionService $selectionService;

    public function __construct(SelectionService $selectionService)
    {
        $this->selectionService = $selectionService;
    }

    public function show(string $projectId, string $id): JsonResponse
    {
        $selection = $this->selectionService->find($projectId, $id);
        return ApiResponse::success(new SelectionResource($selection));
    }

    public function store(StoreSelectionRequest $request, string $projectId): JsonResponse
    {
        $selection = $this->selectionService->create($projectId, $request->validated());
        return ApiResponse::success(new SelectionResource($selection), 201);
    }

    public function update(UpdateSelectionRequest $request, string $projectId, string $id): JsonResponse
    {
        $selection = $this->selectionService->update($projectId, $id, $request->validated());
        return ApiResponse::success(new SelectionResource($selection));
    }

    public function complete(string $projectId, string $id): JsonResponse
    {
        $selection = $this->selectionService->complete($projectId, $id);
        return ApiResponse::success([
            'id' => $selection->id,
            'status' => $selection->status,
            'selectionCompletedAt' => $selection->selection_completed_at?->toIso8601String(),
            'autoDeleteDate' => $selection->auto_delete_date?->toIso8601String(),
        ]);
    }

    public function recover(RecoverMediaRequest $request, string $projectId, string $id): JsonResponse
    {
        $result = $this->selectionService->recover($projectId, $id, $request->validated()['mediaIds']);
        return ApiResponse::success($result);
    }

    public function getSelectedMedia(Request $request, string $projectId, string $id): JsonResponse
    {
        $setId = $request->query('setId');
        $media = $this->selectionService->getSelectedMedia($projectId, $id, $setId);
        return ApiResponse::success(MediaResource::collection($media));
    }

    public function getSelectedFilenames(string $projectId, string $id): JsonResponse
    {
        $result = $this->selectionService->getSelectedFilenames($projectId, $id);
        return ApiResponse::success($result);
    }
}

