<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Services\Pagination\PaginationService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactSubmissionController extends Controller
{
    public function __construct(
        protected PaginationService $paginationService
    ) {}

    /**
     * List contact form submissions (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContactSubmission::query()->orderByDesc('created_at');

        if ($request->filled('search')) {
            $term = $request->query('search');
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('company', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%");
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 100);
        $paginator = $this->paginationService->paginate($query, $perPage);

        $items = $paginator->getCollection()->map(fn (ContactSubmission $s) => [
            'uuid' => $s->uuid,
            'first_name' => $s->first_name,
            'last_name' => $s->last_name,
            'company' => $s->company,
            'email' => $s->email,
            'country' => $s->country,
            'phone' => $s->phone,
            'message' => $s->message,
            'created_at' => $s->created_at?->toIso8601String(),
        ]);

        return ApiResponse::success([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Show a single contact submission.
     */
    public function show(string $uuid): JsonResponse
    {
        $submission = ContactSubmission::where('uuid', $uuid)->firstOrFail();

        return ApiResponse::success([
            'uuid' => $submission->uuid,
            'first_name' => $submission->first_name,
            'last_name' => $submission->last_name,
            'company' => $submission->company,
            'email' => $submission->email,
            'country' => $submission->country,
            'phone' => $submission->phone,
            'message' => $submission->message,
            'created_at' => $submission->created_at?->toIso8601String(),
        ]);
    }
}
