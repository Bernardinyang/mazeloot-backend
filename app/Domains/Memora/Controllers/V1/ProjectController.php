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
     * List projects with optional search, filter, and pagination parameters
     * GET /api/v1/projects
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->query('status', 'all'),
            'search' => $request->query('search'),
            'sortBy' => $request->query('sortBy'),
            'parentId' => $request->query('parentId'),
        ];

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10))); // Limit between 1 and 100

        $result = $this->projectService->list($filters, $page, $perPage);

        return ApiResponse::success($result);
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
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $project = $this->projectService->create($request->validated(), $user->uuid);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'created',
                $project,
                'Project created',
                ['project_uuid' => $project->uuid, 'name' => $project->name],
                $user,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log project activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new ProjectResource($project), 201);
    }

    /**
     * Update a project
     * PATCH /api/v1/projects/:id
     */
    public function update(UpdateProjectRequest $request, string $id): JsonResponse
    {
        $project = $this->projectService->update($id, $request->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'updated',
                $project,
                'Project updated',
                ['project_uuid' => $project->uuid, 'name' => $project->name],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log project activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new ProjectResource($project));
    }

    /**
     * Delete a project
     * DELETE /api/v1/projects/:id
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $project = \App\Domains\Memora\Models\MemoraProject::where('uuid', $id)->where('user_uuid', $request->user()->uuid)->first();
        $this->projectService->delete($id);

        if ($project) {
            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'deleted',
                    null,
                    'Project deleted',
                    ['project_uuid' => $id, 'name' => $project->name],
                    $request->user(),
                    $request
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to log project activity', ['error' => $e->getMessage()]);
            }
        }

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

    /**
     * Toggle star status for a project
     * POST /api/v1/projects/:id/star
     */
    public function toggleStar(Request $request, string $id): JsonResponse
    {
        $result = $this->projectService->toggleStar($id);

        try {
            $project = \App\Domains\Memora\Models\MemoraProject::find($id);
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                $result['starred'] ? 'project_starred' : 'project_unstarred',
                $project,
                $result['starred'] ? 'Project starred' : 'Project unstarred',
                ['project_uuid' => $id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log project activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success($result);
    }
}
