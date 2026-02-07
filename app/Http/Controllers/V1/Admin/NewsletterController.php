<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use App\Services\Pagination\PaginationService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function __construct(
        protected PaginationService $paginationService
    ) {}

    /**
     * List newsletter subscriptions (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Newsletter::query()
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $term = $request->query('search');
            $query->where('email', 'like', "%{$term}%");
        }

        if ($request->filled('status')) {
            if ($request->query('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->query('status') === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 100);
        $paginator = $this->paginationService->paginate($query, $perPage);

        $items = $paginator->getCollection()->map(fn (Newsletter $n) => [
            'uuid' => $n->uuid,
            'email' => $n->email,
            'is_active' => $n->is_active,
            'unsubscribed_at' => $n->unsubscribed_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
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
}
