<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreProjectRequest;
use App\Domains\Memora\Requests\V1\UpdateProjectRequest;
use App\Domains\Memora\Resources\V1\ProjectResource;
use App\Domains\Memora\Services\ProjectService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    protected ProjectService $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    /**
     * List projects
     * GET /api/v1/projects
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->query('status', 'all'),
            'search' => $request->query('search'),
            'parentId' => $request->query('parentId'),
        ];

        $projects = $this->projectService->list($filters);

        return ApiResponse::success(ProjectResource::collection($projects));
    }

    /**
     * Get a single project
     * GET /api/v1/projects/:id
     */
    public function show(string $id): JsonResponse
    {
        $project = $this->projectService->find($id);
        return ApiResponse::success(new ProjectResource($project));
    }

    /**
     * Create a new project
     * POST /api/v1/projects
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->create($request->validated(), $request->user()->id);
        return ApiResponse::success(new ProjectResource($project), 201);
    }

    /**
     * Update a project
     * PATCH /api/v1/projects/:id
     */
    public function update(UpdateProjectRequest $request, string $id): JsonResponse
    {
        $project = $this->projectService->update($id, $request->validated());
        return ApiResponse::success(new ProjectResource($project));
    }

    /**
     * Delete a project
     * DELETE /api/v1/projects/:id
     */
    public function destroy(string $id): JsonResponse
    {
        $this->projectService->delete($id);
        return ApiResponse::success(null, 204);
    }

    /**
     * Get project phases
     * GET /api/v1/projects/:id/phases
     */
    public function phases(string $id): JsonResponse
    {
        $phases = $this->projectService->getPhases($id);
        return ApiResponse::success($phases);
    }
}

