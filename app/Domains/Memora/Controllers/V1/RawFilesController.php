<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\SetCoverPhotoRequest;
use App\Domains\Memora\Requests\V1\StoreRawFilesRequest;
use App\Domains\Memora\Requests\V1\UpdateRawFilesRequest;
use App\Domains\Memora\Services\RawFilesService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RawFilesController extends Controller
{
    protected RawFilesService $rawFilesService;

    public function __construct(RawFilesService $rawFilesService)
    {
        $this->rawFilesService = $rawFilesService;
    }

    public function index(Request $request): JsonResponse
    {
        $projectUuid = $request->query('project_uuid');
        $search = $request->query('search');
        $sortBy = $request->query('sort_by');
        $status = $request->query('status');
        $starred = $request->has('starred') ? filter_var($request->query('starred'), FILTER_VALIDATE_BOOLEAN) : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $result = $this->rawFilesService->getAll(
            projectUuid: $projectUuid,
            search: $search,
            sortBy: $sortBy,
            status: $status,
            starred: $starred,
            page: $page,
            perPage: $perPage
        );

        return ApiResponse::success($result);
    }

    public function show(string $id): JsonResponse
    {
        $rawFiles = $this->rawFilesService->find($id);

        return ApiResponse::success($rawFiles);
    }

    public function store(StoreRawFilesRequest $request): JsonResponse
    {
        $rawFiles = $this->rawFilesService->create($request->validated());

        return ApiResponse::success($rawFiles, 201);
    }

    public function update(UpdateRawFilesRequest $request, string $id): JsonResponse
    {
        $rawFiles = $this->rawFilesService->update($id, $request->validated());

        return ApiResponse::success($rawFiles);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->rawFilesService->delete($id);

        return ApiResponse::success(null, 204);
    }

    public function publish(string $id): JsonResponse
    {
        $rawFiles = $this->rawFilesService->publish($id);

        return ApiResponse::success($rawFiles);
    }

    public function setCoverPhoto(SetCoverPhotoRequest $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validated();
            $focalPoint = $validated['focal_point'] ?? null;
            $rawFiles = $this->rawFilesService->setCoverPhotoFromMedia($id, $validated['media_uuid'], $focalPoint);

            return ApiResponse::success($rawFiles);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw Files phase or media not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'INVALID_MEDIA', 400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to set cover photo', [
                'raw_files_id' => $id,
                'media_uuid' => $request->input('media_uuid'),
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to set cover photo', 'SET_COVER_FAILED', 500);
        }
    }

    public function toggleStar(string $id): JsonResponse
    {
        $result = $this->rawFilesService->toggleStar($id);

        return ApiResponse::success($result);
    }

    public function duplicate(string $id): JsonResponse
    {
        $duplicated = $this->rawFilesService->duplicate($id);

        return ApiResponse::success($duplicated, 201);
    }
}
