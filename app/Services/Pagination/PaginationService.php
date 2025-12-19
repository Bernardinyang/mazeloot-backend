<?php

namespace App\Services\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaginationService
{
    /**
     * Paginate a collection or query
     *
     * @param mixed $items MemoraCollection or Query Builder
     * @param int $perPage
     * @param int|null $page
     * @param string $pageName
     * @return LengthAwarePaginator
     */
    public function paginate($items, int $perPage = 50, ?int $page = null, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = $page ?? request()->get($pageName, 1);

        if ($items instanceof Collection) {
            $items = $items->values();
            $total = $items->count();
            $items = $items->slice(($page - 1) * $perPage, $perPage);

            return new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'pageName' => $pageName,
                ]
            );
        }

        // For query builders, use Laravel's built-in pagination
        return $items->paginate($perPage, ['*'], $pageName, $page);
    }

    /**
     * Format paginated response matching frontend contract
     *
     * @param LengthAwarePaginator $paginator
     * @return array
     */
    public function formatResponse(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ];
    }
}
