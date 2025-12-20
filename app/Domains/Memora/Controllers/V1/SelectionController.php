<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\CompleteSelectionRequest;
use App\Domains\Memora\Requests\V1\RecoverMediaRequest;
use App\Domains\Memora\Requests\V1\StoreSelectionRequest;
use App\Domains\Memora\Requests\V1\UpdateSelectionRequest;
use App\Domains\Memora\Resources\V1\MediaResource;
use App\Domains\Memora\Resources\V1\SelectionResource;
use App\Domains\Memora\Services\GuestSelectionService;
use App\Domains\Memora\Services\SelectionService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelectionController extends Controller
{
    protected SelectionService $selectionService;
    protected GuestSelectionService $guestSelectionService;

    public function __construct(SelectionService $selectionService, GuestSelectionService $guestSelectionService)
    {
        $this->selectionService = $selectionService;
        $this->guestSelectionService = $guestSelectionService;
    }

    /**
     * Get all selections with optional search, sort, filter, and pagination parameters
     */
    public function index(Request $request): JsonResponse
    {
        $projectUuid = $request->query('project_uuid');
        $search = $request->query('search');
        $sortBy = $request->query('sort_by');
        $status = $request->query('status');
        $starred = $request->has('starred') ? filter_var($request->query('starred'), FILTER_VALIDATE_BOOLEAN) : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 50))); // Limit between 1 and 100

        $result = $this->selectionService->getAll(
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
     * Get a selection by ID
     */
    public function show(string $id): JsonResponse
    {
        $selection = $this->selectionService->find($id);
        return ApiResponse::success($selection);
    }

    /**
     * Create a selection (standalone if project_uuid is null, project-based if provided)
     */
    public function store(StoreSelectionRequest $request): JsonResponse
    {
        $selection = $this->selectionService->create($request->validated());
        return ApiResponse::success($selection, 201);
    }

    /**
     * Update a selection
     */
    public function update(UpdateSelectionRequest $request, string $id): JsonResponse
    {
        $selection = $this->selectionService->update($id, $request->validated());
        return ApiResponse::success($selection);
    }

    /**
     * Delete a selection
     */
    public function destroy(string $id): JsonResponse
    {
        $this->selectionService->delete($id);
        return ApiResponse::success(null, 204);
    }

    /**
     * Publish a selection (creative can only publish to active, not complete)
     */
    public function publish(string $id): JsonResponse
    {
        $selection = $this->selectionService->publish($id);
        return ApiResponse::success($selection);
    }

    /**
     * Recover deleted media
     */
    public function recover(RecoverMediaRequest $request, string $id): JsonResponse
    {
        $result = $this->selectionService->recover($id, $request->validated()['mediaIds']);
        return ApiResponse::success($result);
    }

    /**
     * Get selected media
     */
    public function getSelectedMedia(Request $request, string $id): JsonResponse
    {
        $setUuid = $request->query('setId');
        $media = $this->selectionService->getSelectedMedia($id, $setUuid);
        return ApiResponse::success(MediaResource::collection($media));
    }

    /**
     * Get selected filenames
     */
    public function getSelectedFilenames(string $id): JsonResponse
    {
        $result = $this->selectionService->getSelectedFilenames($id);
        return ApiResponse::success($result);
    }

    /**
     * Toggle star status for a selection
     */
    public function toggleStar(string $id): JsonResponse
    {
        $result = $this->selectionService->toggleStar($id);
        return ApiResponse::success($result);
    }

    // Guest methods (for guest users with temporary tokens)

    /**
     * Get a selection (guest access)
     */
    public function showGuest(Request $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        $selection = $this->selectionService->find($id);
        return ApiResponse::success($selection);
    }

    /**
     * Complete a selection (guest access)
     * Accepts media UUIDs to mark as selected
     */
    public function completeGuest(CompleteSelectionRequest $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        // Complete selection with media UUIDs and guest email
        $selection = $this->selectionService->complete(
            $id,
            $request->validated()['mediaIds'],
            $guestToken->email
        );

        // Mark token as used
        $this->guestSelectionService->markTokenAsUsed($guestToken->token);

        return ApiResponse::success($selection);
    }
}

