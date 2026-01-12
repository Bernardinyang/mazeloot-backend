<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreProofingRequest;
use App\Domains\Memora\Requests\V1\UpdateProofingRequest;
use App\Domains\Memora\Resources\V1\ProofingResource;
use App\Domains\Memora\Services\ProofingService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use App\Support\Traits\ExtractsRouteParameters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProofingController extends Controller
{
    use ExtractsRouteParameters;
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

    public function show(Request $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);

        $projectId = $request->query('projectId');
        $proofing = $this->proofingService->find($projectId, $id);

        return ApiResponse::success(new ProofingResource($proofing));
    }

    /**
     * Create proofing (unified for standalone and project-based)
     * For project-based: pass projectId in request body or ?projectId=xxx as query parameter
     */
    public function store(StoreProofingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $projectId = $request->input('projectId') ?? $request->query('projectId');
        $data['project_uuid'] = $projectId;
        $proofing = $this->proofingService->create($data);

        return ApiResponse::success(new ProofingResource($proofing), 201);
    }

    /**
     * Update proofing (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function update(UpdateProofingRequest $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);
        $projectId = $request->query('projectId');
        $proofing = $projectId
            ? $this->proofingService->update($projectId, $id, $request->validated())
            : $this->proofingService->updateStandalone($id, $request->validated());

        return ApiResponse::success(new ProofingResource($proofing));
    }

    /**
     * Delete proofing (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);
        $projectId = $request->query('projectId');
        if ($projectId) {
            $this->proofingService->delete($projectId, $id);
        } else {
            $this->proofingService->deleteStandalone($id);
        }

        return ApiResponse::success(null, 204);
    }

    /**
     * Publish proofing (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);
        $projectId = $request->query('projectId');
        $proofing = $projectId
            ? $this->proofingService->publish($projectId, $id)
            : $this->proofingService->publishStandalone($id);

        return ApiResponse::success(new ProofingResource($proofing));
    }

    /**
     * Toggle star status (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function toggleStar(Request $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);
        $projectId = $request->query('projectId');
        $result = $projectId
            ? $this->proofingService->toggleStar($projectId, $id)
            : $this->proofingService->toggleStarStandalone($id);

        return ApiResponse::success($result);
    }

    /**
     * Duplicate proofing (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);
        $projectId = $request->query('projectId');
        $duplicated = $this->proofingService->duplicate($projectId, $id);

        return ApiResponse::success(new ProofingResource($duplicated), 201);
    }

    /**
     * Set cover photo from media (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function setCoverPhoto(Request $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);
        $validated = $request->validate([
            'media_uuid' => 'required|uuid',
            'focal_point' => 'nullable|array',
        ]);

        try {
            $projectId = $request->query('projectId');
            $focalPoint = $validated['focal_point'] ?? null;
            $proofing = $projectId
                ? $this->proofingService->setCoverPhotoFromMedia($projectId, $id, $validated['media_uuid'], $focalPoint)
                : $this->proofingService->setCoverPhotoFromMediaStandalone($id, $validated['media_uuid'], $focalPoint);

            return ApiResponse::success(new ProofingResource($proofing));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Proofing or media not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'INVALID_MEDIA', 400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to set cover photo', [
                'project_id' => $request->query('projectId'),
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
     * Recover deleted media (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function recover(Request $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);
        $request->validate([
            'mediaIds' => 'required|array',
            'mediaIds.*' => 'uuid',
        ]);

        $projectId = $request->query('projectId');
        $result = $projectId
            ? $this->proofingService->recover($projectId, $id, $request->input('mediaIds'))
            : $this->proofingService->recoverStandalone($id, $request->input('mediaIds'));

        return ApiResponse::success($result);
    }

    /**
     * Upload revision (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function uploadRevision(Request $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);
        $request->validate([
            'mediaId' => 'required|uuid',
            'userFileUuid' => 'required|uuid',
            'revisionNumber' => 'required|integer|min:1',
            'description' => 'nullable|string|max:1000',
            'completedTodos' => 'nullable|array',
            'completedTodos.*' => 'integer',
        ]);

        $projectId = $request->query('projectId');
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

    /**
     * Complete proofing (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);
        $projectId = $request->query('projectId');
        $proofing = $projectId
            ? $this->proofingService->complete($projectId, $id)
            : $this->proofingService->completeStandalone($id);

        return ApiResponse::success([
            'id' => $proofing->id,
            'status' => $proofing->status,
            'completedAt' => $proofing->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * Move media to collection (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function moveToCollection(Request $request, string $id): JsonResponse
    {
        $id = $this->getRouteParameter($request, 'id', $id);
        $request->validate([
            'mediaIds' => 'required|array',
            'mediaIds.*' => 'uuid',
            'collectionId' => 'required|uuid',
        ]);

        $projectId = $request->query('projectId');
        $result = $this->proofingService->moveToCollection(
            $projectId,
            $id,
            $request->input('mediaIds'),
            $request->input('collectionId')
        );

        return ApiResponse::success($result);
    }
}
