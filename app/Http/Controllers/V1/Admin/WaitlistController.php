<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Waitlist;
use App\Services\Pagination\PaginationService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function __construct(
        protected PaginationService $paginationService
    ) {}

    /**
     * List waitlist entries (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Waitlist::query()
            ->with('product')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $term = $request->query('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('product_uuid')) {
            $query->where('product_uuid', $request->query('product_uuid'));
        }

        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 100);
        $paginator = $this->paginationService->paginate($query, $perPage);

        $items = $paginator->getCollection()->map(fn (Waitlist $w) => [
            'uuid' => $w->uuid,
            'name' => $w->name,
            'email' => $w->email,
            'product' => $w->product ? [
                'uuid' => $w->product->uuid,
                'name' => $w->product->name,
                'slug' => $w->product->slug,
            ] : null,
            'status' => $w->status,
            'user_uuid' => $w->user_uuid,
            'registered_at' => $w->registered_at?->toIso8601String(),
            'created_at' => $w->created_at?->toIso8601String(),
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
     * Show a single waitlist entry.
     */
    public function show(string $uuid): JsonResponse
    {
        $waitlist = Waitlist::with(['product', 'user'])->where('uuid', $uuid)->firstOrFail();

        return ApiResponse::success([
            'uuid' => $waitlist->uuid,
            'name' => $waitlist->name,
            'email' => $waitlist->email,
            'product' => $waitlist->product ? [
                'uuid' => $waitlist->product->uuid,
                'name' => $waitlist->product->name,
                'slug' => $waitlist->product->slug,
            ] : null,
            'status' => $waitlist->status,
            'user' => $waitlist->user ? [
                'uuid' => $waitlist->user->uuid,
                'email' => $waitlist->user->email,
                'name' => $waitlist->user->first_name.' '.$waitlist->user->last_name,
            ] : null,
            'registered_at' => $waitlist->registered_at?->toIso8601String(),
            'created_at' => $waitlist->created_at?->toIso8601String(),
        ]);
    }
}
