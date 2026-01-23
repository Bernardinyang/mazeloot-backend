<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraRawFile;
use App\Domains\Memora\Models\MemoraPreset;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    /**
     * Unified search across all content types
     */
    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();
        $search = $request->query('search', '');
        $type = $request->query('type'); // Filter by content type
        $status = $request->query('status');
        $starred = $request->has('starred') ? filter_var($request->query('starred'), FILTER_VALIDATE_BOOLEAN) : null;
        $products = $request->query('products'); // Array of product UUIDs
        $limit = min(50, max(1, (int) $request->query('limit', 50)));

        // Log search request for debugging
        Log::info('Search request', [
            'user' => $user->uuid,
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'starred' => $starred,
            'products' => $products,
            'limit' => $limit,
        ]);

        $results = [];

        // Search Collections
        if (!$type || $type === 'collection') {
            $collections = MemoraCollection::where('user_uuid', $user->uuid)
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                })
                ->when($status, fn($q) => $q->where('status', $status))
                ->when($starred !== null, function ($q) use ($user, $starred) {
                    if ($starred) {
                        $q->whereHas('starredByUsers', fn($q) => $q->where('user_uuid', $user->uuid));
                    } else {
                        $q->whereDoesntHave('starredByUsers', fn($q) => $q->where('user_uuid', $user->uuid));
                    }
                })
                ->limit($limit)
                ->get()
                ->map(fn($item) => [
                    'id' => $item->uuid,
                    'name' => $item->name,
                    'description' => $item->description,
                    'type' => 'collection',
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                ]);

            $results = array_merge($results, $collections->toArray());
        }

        // Search Projects
        if (!$type || $type === 'project') {
            $projects = MemoraProject::where('user_uuid', $user->uuid)
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                })
                ->when($status, fn($q) => $q->where('status', $status))
                ->when($starred !== null, function ($q) use ($user, $starred) {
                    if ($starred) {
                        $q->whereHas('starredByUsers', fn($q) => $q->where('user_uuid', $user->uuid));
                    } else {
                        $q->whereDoesntHave('starredByUsers', fn($q) => $q->where('user_uuid', $user->uuid));
                    }
                })
                ->limit($limit)
                ->get()
                ->map(fn($item) => [
                    'id' => $item->uuid,
                    'name' => $item->name,
                    'description' => $item->description,
                    'type' => 'project',
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                ]);

            $results = array_merge($results, $projects->toArray());
        }

        // Search Selections
        if (!$type || $type === 'selection') {
            $selections = MemoraSelection::where('user_uuid', $user->uuid)
                ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
                ->when($status, fn($q) => $q->where('status', $status))
                ->when($starred !== null, function ($q) use ($user, $starred) {
                    if ($starred) {
                        $q->whereHas('starredByUsers', fn($q) => $q->where('user_uuid', $user->uuid));
                    } else {
                        $q->whereDoesntHave('starredByUsers', fn($q) => $q->where('user_uuid', $user->uuid));
                    }
                })
                ->limit($limit)
                ->get()
                ->map(fn($item) => [
                    'id' => $item->uuid,
                    'name' => $item->name,
                    'description' => null,
                    'type' => 'selection',
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                ]);

            $results = array_merge($results, $selections->toArray());
        }

        // Search Proofing
        if (!$type || $type === 'proofing') {
            $proofing = MemoraProofing::where('user_uuid', $user->uuid)
                ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
                ->when($status, fn($q) => $q->where('status', $status))
                ->when($starred !== null, function ($q) use ($user, $starred) {
                    if ($starred) {
                        $q->whereHas('starredByUsers', fn($q) => $q->where('user_uuid', $user->uuid));
                    } else {
                        $q->whereDoesntHave('starredByUsers', fn($q) => $q->where('user_uuid', $user->uuid));
                    }
                })
                ->limit($limit)
                ->get()
                ->map(fn($item) => [
                    'id' => $item->uuid,
                    'name' => $item->name,
                    'description' => null,
                    'type' => 'proofing',
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                ]);

            $results = array_merge($results, $proofing->toArray());
        }

        // Search Raw Files
        if (!$type || $type === 'rawFile') {
            $rawFiles = MemoraRawFile::where('user_uuid', $user->uuid)
                ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
                ->when($status, fn($q) => $q->where('status', $status))
                ->when($starred !== null, function ($q) use ($user, $starred) {
                    if ($starred) {
                        $q->whereHas('starredByUsers', fn($q) => $q->where('user_uuid', $user->uuid));
                    } else {
                        $q->whereDoesntHave('starredByUsers', fn($q) => $q->where('user_uuid', $user->uuid));
                    }
                })
                ->limit($limit)
                ->get()
                ->map(fn($item) => [
                    'id' => $item->uuid,
                    'name' => $item->name,
                    'description' => null,
                    'type' => 'rawFile',
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                ]);

            $results = array_merge($results, $rawFiles->toArray());
        }

        // Search Presets
        if (!$type || $type === 'preset') {
            $presets = MemoraPreset::where('user_uuid', $user->uuid)
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%");
                    });
                })
                ->limit($limit)
                ->get()
                ->map(fn($item) => [
                    'id' => $item->uuid,
                    'name' => $item->name,
                    'description' => $item->description,
                    'type' => 'preset',
                    'status' => null,
                    'created_at' => $item->created_at,
                ]);

            $results = array_merge($results, $presets->toArray());
        }

        // Sort by relevance (exact match first, then created_at desc)
        usort($results, function ($a, $b) use ($search) {
            if ($search) {
                $aExact = stripos($a['name'], $search) === 0;
                $bExact = stripos($b['name'], $search) === 0;
                if ($aExact !== $bExact) {
                    return $aExact ? -1 : 1;
                }
            }
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // Add productUuid to each result (currently all Memora content)
        $results = array_map(function ($item) {
            $item['productUuid'] = 'memora'; // TODO: Map to actual product UUID when multi-product support is added
            return $item;
        }, $results);

        Log::info('Search results', [
            'user' => $user->uuid,
            'count' => count($results),
        ]);

        return ApiResponse::success($results);
    }
}
