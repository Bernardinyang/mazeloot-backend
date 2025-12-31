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

    /**
     * Get all proofing with optional search, sort, filter, and pagination parameters
     */
    public function index(Request $request): JsonResponse
    {
        $projectUuid = $request->query('project_uuid');
        $search = $request->query('search');
        $sortBy = $request->query('sort_by');
        $status = $request->query('status');
        $starred = $request->has('starred') ? filter_var($request->query('starred'), FILTER_VALIDATE_BOOLEAN) : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10))); // Limit between 1 and 100

        $result = $this->proofingService->getAll(
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

    /**
     * Show standalone proofing (not tied to a project)
     */
    public function showStandalone(string $id): JsonResponse
    {
        $proofing = $this->proofingService->find(null, $id);

        return ApiResponse::success(new ProofingResource($proofing));
    }

    /**
     * Show project-based proofing
     */
    public function show(string $projectId, string $id): JsonResponse
    {
        $proofing = $this->proofingService->find($projectId, $id);

        return ApiResponse::success(new ProofingResource($proofing));
    }

    /**
     * Create a standalone proofing (not tied to a project)
     */
    public function storeStandalone(StoreProofingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['project_uuid'] = null; // Ensure standalone
        $proofing = $this->proofingService->create($data);

        return ApiResponse::success(new ProofingResource($proofing), 201);
    }

    /**
     * Create a project-based proofing
     */
    public function store(StoreProofingRequest $request, string $projectId): JsonResponse
    {
        $data = $request->validated();
        $data['project_uuid'] = $projectId;
        $proofing = $this->proofingService->create($data);

        return ApiResponse::success(new ProofingResource($proofing), 201);
    }

    /**
     * Update standalone proofing
     */
    public function updateStandalone(UpdateProofingRequest $request, string $id): JsonResponse
    {
        $proofing = $this->proofingService->updateStandalone($id, $request->validated());

        return ApiResponse::success(new ProofingResource($proofing));
    }

    /**
     * Update project-based proofing
     */
    public function update(UpdateProofingRequest $request, string $projectId, string $id): JsonResponse
    {
        $proofing = $this->proofingService->update($projectId, $id, $request->validated());

        return ApiResponse::success(new ProofingResource($proofing));
    }

    /**
     * Delete standalone proofing
     */
    public function destroyStandalone(string $id): JsonResponse
    {
        $this->proofingService->deleteStandalone($id);

        return ApiResponse::success(null, 204);
    }

    /**
     * Delete project-based proofing
     */
    public function destroy(string $projectId, string $id): JsonResponse
    {
        $this->proofingService->delete($projectId, $id);

        return ApiResponse::success(null, 204);
    }

    /**
     * Publish standalone proofing
     */
    public function publishStandalone(string $id): JsonResponse
    {
        $proofing = $this->proofingService->publishStandalone($id);

        return ApiResponse::success(new ProofingResource($proofing));
    }

    /**
     * Publish project-based proofing
     */
    public function publish(string $projectId, string $id): JsonResponse
    {
        $proofing = $this->proofingService->publish($projectId, $id);

        return ApiResponse::success(new ProofingResource($proofing));
    }

    /**
     * Toggle star status for standalone proofing
     */
    public function toggleStarStandalone(string $id): JsonResponse
    {
        $result = $this->proofingService->toggleStarStandalone($id);

        return ApiResponse::success($result);
    }

    /**
     * Toggle star status for project-based proofing
     */
    public function toggleStar(string $projectId, string $id): JsonResponse
    {
        $result = $this->proofingService->toggleStar($projectId, $id);

        return ApiResponse::success($result);
    }

    /**
     * Set cover photo from media for standalone proofing
     */
    public function setCoverPhotoStandalone(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'media_uuid' => 'required|uuid',
            'focal_point' => 'nullable|array',
        ]);

        try {
            $focalPoint = $validated['focal_point'] ?? null;
            $proofing = $this->proofingService->setCoverPhotoFromMediaStandalone($id, $validated['media_uuid'], $focalPoint);

            return ApiResponse::success(new ProofingResource($proofing));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Proofing or media not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'INVALID_MEDIA', 400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to set cover photo (standalone)', [
                'proofing_id' => $id,
                'media_uuid' => $request->input('media_uuid'),
                'focal_point' => $request->input('focal_point'),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to set cover photo', 'SET_COVER_FAILED', 500);
        }
    }

    /**
     * Set cover photo from media for project-based proofing
     */
    public function setCoverPhoto(Request $request, string $projectId, string $id): JsonResponse
    {
        $validated = $request->validate([
            'media_uuid' => 'required|uuid',
            'focal_point' => 'nullable|array',
        ]);

        try {
            $focalPoint = $validated['focal_point'] ?? null;
            $proofing = $this->proofingService->setCoverPhotoFromMedia($projectId, $id, $validated['media_uuid'], $focalPoint);

            return ApiResponse::success(new ProofingResource($proofing));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Proofing or media not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'INVALID_MEDIA', 400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to set cover photo (project-based)', [
                'project_id' => $projectId,
                'proofing_id' => $id,
                'media_uuid' => $request->input('media_uuid'),
                'focal_point' => $request->input('focal_point'),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to set cover photo', 'SET_COVER_FAILED', 500);
        }
    }

    /**
     * Recover deleted media for standalone proofing
     */
    public function recoverStandalone(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'mediaIds' => 'required|array',
            'mediaIds.*' => 'uuid',
        ]);

        $result = $this->proofingService->recoverStandalone($id, $request->input('mediaIds'));

        return ApiResponse::success($result);
    }

    /**
     * Recover deleted media for project-based proofing
     */
    public function recover(Request $request, string $projectId, string $id): JsonResponse
    {
        $request->validate([
            'mediaIds' => 'required|array',
            'mediaIds.*' => 'uuid',
        ]);

        $result = $this->proofingService->recover($projectId, $id, $request->input('mediaIds'));

        return ApiResponse::success($result);
    }

    public function uploadRevision(Request $request, string $projectId, string $id): JsonResponse
    {
        $request->validate([
            'mediaId' => 'required|uuid',
            'userFileUuid' => 'required|uuid',
            'revisionNumber' => 'required|integer|min:1',
            'description' => 'nullable|string|max:1000',
            'completedTodos' => 'nullable|array',
            'completedTodos.*' => 'integer',
        ]);

        $revision = $this->proofingService->uploadRevision(
            $projectId,
            $id,
            $request->input('mediaId'),
            $request->input('revisionNumber'),
            $request->input('description', ''),
            $request->input('userFileUuid'),
            $request->input('completedTodos', [])
        );

        return ApiResponse::success(new \App\Domains\Memora\Resources\V1\MediaResource($revision), 201);
    }

    public function uploadRevisionStandalone(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'mediaId' => 'required|uuid',
            'userFileUuid' => 'required|uuid',
            'revisionNumber' => 'required|integer|min:1',
            'description' => 'nullable|string|max:1000',
            'completedTodos' => 'nullable|array',
            'completedTodos.*' => 'integer',
        ]);

        $revision = $this->proofingService->uploadRevision(
            null,
            $id,
            $request->input('mediaId'),
            $request->input('revisionNumber'),
            $request->input('description', ''),
            $request->input('userFileUuid'),
            $request->input('completedTodos', [])
        );

        return ApiResponse::success(new \App\Domains\Memora\Resources\V1\MediaResource($revision), 201);
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

    public function completeStandalone(string $id): JsonResponse
    {
        $proofing = $this->proofingService->completeStandalone($id);

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
