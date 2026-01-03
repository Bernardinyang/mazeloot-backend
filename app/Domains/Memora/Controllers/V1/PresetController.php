<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StorePresetRequest;
use App\Domains\Memora\Requests\V1\UpdatePresetRequest;
use App\Domains\Memora\Resources\V1\PresetResource;
use App\Domains\Memora\Services\PresetService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PresetController extends Controller
{
    protected PresetService $presetService;

    public function __construct(PresetService $presetService)
    {
        $this->presetService = $presetService;
    }

    /**
     * Get user's presets
     */
    public function index(): JsonResponse
    {
        $search = request()->query('search');
        $sortBy = request()->query('sort_by', 'created_at');
        $sortOrder = request()->query('sort_order', 'desc');
        $presets = $this->presetService->getByUser($search, $sortBy, $sortOrder);

        return ApiResponse::success(PresetResource::collection($presets));
    }

    /**
     * Get single preset by ID or name
     */
    public function show(string $id): JsonResponse
    {
        // Try to get by ID first (UUID)
        try {
            $preset = $this->presetService->getById($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // If not found by ID, try by name
            $preset = $this->presetService->getByName($id);
            if (! $preset) {
                throw $e;
            }
        }

        return ApiResponse::success(new PresetResource($preset));
    }

    /**
     * Create a preset
     */
    public function store(StorePresetRequest $request): JsonResponse
    {
        $preset = $this->presetService->create($request->validated());

        return ApiResponse::success(new PresetResource($preset), 201);
    }

    /**
     * Update a preset
     */
    public function update(UpdatePresetRequest $request, string $id): JsonResponse
    {
        $preset = $this->presetService->update($id, $request->validated());

        return ApiResponse::success(new PresetResource($preset));
    }

    /**
     * Delete a preset
     */
    public function destroy(string $id): JsonResponse
    {
        $this->presetService->delete($id);

        return ApiResponse::success(null, 204);
    }

    /**
     * Duplicate a preset
     */
    public function duplicate(string $id): JsonResponse
    {
        $duplicated = $this->presetService->duplicate($id);

        return ApiResponse::success(new PresetResource($duplicated), 201);
    }

    /**
     * Apply preset to collection
     */
    public function applyToCollection(string $id, string $collectionId): JsonResponse
    {
        $collection = $this->presetService->applyToCollection($id, $collectionId);

        return ApiResponse::success(['message' => 'Preset applied successfully']);
    }

    /**
     * Get preset usage count
     */
    public function usage(string $id): JsonResponse
    {
        $count = $this->presetService->getUsageCount($id);

        return ApiResponse::success(['count' => $count]);
    }

    /**
     * Set preset as default
     */
    public function setDefault(string $id): JsonResponse
    {
        $preset = $this->presetService->setAsDefault($id);

        return ApiResponse::success(new PresetResource($preset));
    }

    /**
     * Reorder presets
     */
    public function reorder(): JsonResponse
    {
        $request = request();
        $presetIds = $request->input('preset_ids', []);

        if (! is_array($presetIds) || empty($presetIds)) {
            return ApiResponse::error('preset_ids array is required', 422);
        }

        $this->presetService->reorder($presetIds);

        return ApiResponse::success(['message' => 'Presets reordered successfully']);
    }
}
