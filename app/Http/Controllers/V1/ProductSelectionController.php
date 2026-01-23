<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreProductSelectionRequest;
use App\Models\Product;
use App\Models\UserProductSelection;
use App\Services\ProductService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductSelectionController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * Get user's selected products.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $selectedProducts = $this->productService->getUserSelectedProducts($user);

        return ApiResponse::success($selectedProducts);
    }

    /**
     * Store user's product selections.
     */
    public function store(StoreProductSelectionRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $validated = $request->validated();
            
            if (!isset($validated['products']) || !is_array($validated['products'])) {
                return ApiResponse::errorValidation('Products array is required and must not be empty');
            }
            
            $productUuids = array_values(array_filter($validated['products'], fn($uuid) => is_string($uuid) && !empty($uuid)));
            $token = $request->input('token'); // Optional token to mark as used

            if (empty($productUuids)) {
                return ApiResponse::errorValidation('At least one valid product UUID is required');
            }

            // Validate all products exist, are active, and only memora is allowed for now
            $products = Product::whereIn('uuid', $productUuids)
                ->where('is_active', true)
                ->where('slug', 'memora')
                ->get();

            if ($products->count() !== count($productUuids)) {
                return ApiResponse::errorValidation('Only Memora is available for selection at this time. Other products are coming soon.');
            }

            DB::transaction(function () use ($user, $productUuids, $token) {
                // Delete existing selections
                DB::table('user_product_selections')
                    ->where('user_uuid', $user->uuid)
                    ->delete();

                // Create new selections using DB facade to avoid composite key issues
                $now = now();
                $inserts = [];
                foreach ($productUuids as $productUuid) {
                    $inserts[] = [
                        'user_uuid' => $user->uuid,
                        'product_uuid' => $productUuid,
                        'selected_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                
                if (!empty($inserts)) {
                    DB::table('user_product_selections')->insert($inserts);
                }

                // Mark product selection token as used if provided
                if ($token) {
                    Cache::forget("product_selection_token:{$token}");
                }
            });

            $selectedProducts = $this->productService->getUserSelectedProducts($user);

            return ApiResponse::successCreated($selectedProducts->values());
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Product selection error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);
            return ApiResponse::error('Failed to process product selection: ' . $e->getMessage(), 'PROCESSING_ERROR', 500);
        }
    }
}
