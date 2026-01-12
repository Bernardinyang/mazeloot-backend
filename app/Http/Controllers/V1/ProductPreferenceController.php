<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SetupProductRequest;
use App\Http\Requests\V1\StoreProductPreferenceRequest;
use App\Http\Resources\V1\UserProductPreferenceResource;
use App\Models\Product;
use App\Models\UserProductPreference;
use App\Services\Product\MemoraSetupHandler;
use App\Services\Product\SubdomainResolutionService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductPreferenceController extends Controller
{
    protected MemoraSetupHandler $memoraSetupHandler;
    protected SubdomainResolutionService $subdomainResolutionService;

    public function __construct(
        MemoraSetupHandler $memoraSetupHandler,
        SubdomainResolutionService $subdomainResolutionService
    ) {
        $this->memoraSetupHandler = $memoraSetupHandler;
        $this->subdomainResolutionService = $subdomainResolutionService;
    }

    /**
     * Save selected products for the authenticated user
     */
    public function store(StoreProductPreferenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $productUuids = $request->validated()['products'];

        DB::beginTransaction();
        try {
            // Delete existing preferences (user can reselect)
            UserProductPreference::where('user_uuid', $user->uuid)->delete();

            // Create new preferences
            $preferences = [];
            foreach ($productUuids as $productUuid) {
                $preferences[] = UserProductPreference::create([
                    'user_uuid' => $user->uuid,
                    'product_uuid' => $productUuid,
                ]);
            }

            DB::commit();

            return ApiResponse::successCreated(
                UserProductPreferenceResource::collection(collect($preferences))
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save product preferences', [
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Failed to save product preferences',
                'SAVE_FAILED',
                500
            );
        }
    }

    /**
     * Get user's selected products
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $preferences = UserProductPreference::where('user_uuid', $user->uuid)
            ->with('product')
            ->get();

        return ApiResponse::success(UserProductPreferenceResource::collection($preferences));
    }

    /**
     * Initialize product setup
     */
    public function setup(string $productId, SetupProductRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Find product by ID (slug or uuid)
        $product = Product::where('id', $productId)
            ->orWhere('slug', $productId)
            ->orWhere('uuid', $productId)
            ->first();

        if (!$product) {
            return ApiResponse::error(
                'Product not found',
                'PRODUCT_NOT_FOUND',
                404
            );
        }

        // Check if user has selected this product
        $preference = UserProductPreference::where('user_uuid', $user->uuid)
            ->where('product_uuid', $product->uuid)
            ->first();

        if (!$preference) {
            return ApiResponse::error(
                'You have not selected this product',
                'PRODUCT_NOT_SELECTED',
                403
            );
        }

        DB::beginTransaction();
        try {
            // Handle product-specific setup
            if ($product->id === 'memora') {
                $this->memoraSetupHandler->setup($user->uuid, $data);
            }
            // Add other product handlers here as needed

            // Update preference to mark onboarding as completed
            $preference->update([
                'onboarding_completed' => true,
                'domain' => $data['domain'] ?? $preference->domain,
            ]);

            // Clear domain cache if domain was updated
            if (isset($data['domain'])) {
                $this->subdomainResolutionService->clearCache($data['domain']);
            }

            DB::commit();

            $preference->load('product');

            return ApiResponse::successCreated(new UserProductPreferenceResource($preference));
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return ApiResponse::error(
                $e->getMessage(),
                'INVALID_DOMAIN_FORMAT',
                422
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to setup product', [
                'user_uuid' => $user->uuid,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Failed to setup product',
                'SETUP_FAILED',
                500
            );
        }
    }

    /**
     * Check domain availability
     */
    public function checkDomain(): JsonResponse
    {
        $domain = request()->query('domain');

        if (!$domain) {
            return ApiResponse::error(
                'Domain parameter is required',
                'DOMAIN_MISSING',
                400
            );
        }

        // Validate format
        if (!preg_match('/^[a-z0-9-]+$/', $domain)) {
            return ApiResponse::error(
                'Domain can only contain lowercase letters, numbers, and hyphens.',
                'INVALID_DOMAIN_FORMAT',
                422
            );
        }

        if (strlen($domain) < 3 || strlen($domain) > 63) {
            return ApiResponse::error(
                'Domain must be between 3 and 63 characters.',
                'INVALID_DOMAIN_FORMAT',
                422
            );
        }

        if ($domain[0] === '-' || $domain[strlen($domain) - 1] === '-') {
            return ApiResponse::error(
                'Domain cannot start or end with a hyphen.',
                'INVALID_DOMAIN_FORMAT',
                422
            );
        }

        $reserved = ['admin', 'api', 'www', 'mail', 'ftp', 'localhost', 'test', 'staging', 'dev', 'app'];
        if (in_array(strtolower($domain), $reserved)) {
            return ApiResponse::error(
                'This domain is reserved and cannot be used.',
                'INVALID_DOMAIN_FORMAT',
                422
            );
        }

        // Check availability
        $user = auth()->user();
        $isTaken = UserProductPreference::where('domain', $domain)
            ->where('user_uuid', '!=', $user->uuid)
            ->exists();

        return ApiResponse::success([
            'available' => !$isTaken,
            'domain' => $domain,
        ]);
    }
}
