<?php

namespace App\Http\Controllers\V1;

use App\Domains\Memora\Requests\V1\ValidateDomainRequest;
use App\Domains\Memora\Services\DomainService;
use App\Domains\Memora\Services\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CompleteOnboardingStepRequest;
use App\Http\Requests\V1\GenerateOnboardingTokenRequest;
use App\Models\Product;
use App\Models\UserOnboardingStatus;
use App\Services\OnboardingTokenService;
use App\Services\ProductService;
use App\Services\Subscription\TierService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingTokenService $tokenService,
        protected ProductService $productService,
        protected DomainService $domainService,
        protected SettingsService $settingsService,
        protected TierService $tierService
    ) {}

    /**
     * Generate token for product onboarding.
     */
    public function generateToken(GenerateOnboardingTokenRequest $request): JsonResponse
    {
        $user = Auth::user();
        $productUuid = $request->validated()['product_uuid'];

        // Check if user has selected this product
        $hasSelection = $user->productSelections()
            ->where('product_uuid', $productUuid)
            ->exists();

        if (! $hasSelection) {
            return ApiResponse::errorForbidden('You must select this product first');
        }

        $product = Product::findOrFail($productUuid);
        $token = $this->tokenService->generate($user, $product);

        return ApiResponse::success([
            'token' => $token->token,
            'expires_at' => $token->expires_at,
            'product' => $product,
        ]);
    }

    /**
     * Verify token validity.
     */
    public function verifyToken(Request $request): JsonResponse
    {
        $token = $request->query('token');

        if (! $token) {
            return ApiResponse::errorBadRequest('Token is required');
        }

        // Check product selection token first
        $productSelectionToken = \Illuminate\Support\Facades\Cache::get("product_selection_token:{$token}");
        if ($productSelectionToken) {
            return ApiResponse::success([
                'valid' => true,
                'user_uuid' => $productSelectionToken['user_uuid'],
                'type' => 'product_selection',
                'expires_at' => $productSelectionToken['created_at'],
            ]);
        }

        // Check onboarding token
        $tokenModel = $this->tokenService->verify($token);

        if (! $tokenModel) {
            return ApiResponse::errorUnauthorized('Invalid or expired token');
        }

        return ApiResponse::success([
            'valid' => true,
            'user_uuid' => $tokenModel->user_uuid,
            'product_uuid' => $tokenModel->product_uuid,
            'type' => 'onboarding',
            'expires_at' => $tokenModel->expires_at,
        ]);
    }

    /**
     * Get onboarding status for user.
     */
    public function getStatus(): JsonResponse
    {
        $user = Auth::user();
        $statuses = UserOnboardingStatus::where('user_uuid', $user->uuid)
            ->with('product')
            ->get();

        return ApiResponse::success($statuses);
    }

    /**
     * Complete an onboarding step.
     */
    public function completeStep(CompleteOnboardingStepRequest $request): JsonResponse
    {
        $user = Auth::user();
        $data = $request->validated();
        $productUuid = $data['product_uuid'];
        $step = $data['step'];
        $token = $data['token'];
        $stepData = $data['data'];

        // Verify token
        $tokenModel = $this->tokenService->verify($token);
        if (! $tokenModel || $tokenModel->user_uuid !== $user->uuid || $tokenModel->product_uuid !== $productUuid) {
            return ApiResponse::errorUnauthorized('Invalid or expired token');
        }

        $product = Product::findOrFail($productUuid);

        // Handle Memora-specific steps (onboarding: domain and brand name allowed for all tiers)
        if ($product->slug === 'memora') {
            if ($step === 'domain') {
                $domain = $stepData['domain'] ?? null;
                if (! $domain) {
                    return ApiResponse::errorValidation('Domain is required');
                }

                $validation = $this->domainService->validateDomain($domain, $user->uuid);
                if (! $validation['available']) {
                    return ApiResponse::errorValidation($validation['message']);
                }

                $this->domainService->saveDomain($user, $domain);
            } elseif ($step === 'branding') {
                $name = $stepData['name'] ?? '';
                $this->settingsService->updateBranding(['name' => $name]);
            }
        }

        // Update onboarding status
        $status = UserOnboardingStatus::firstOrCreate(
            [
                'user_uuid' => $user->uuid,
                'product_uuid' => $productUuid,
            ],
            [
                'completed_steps' => [],
                'onboarding_data' => [],
            ]
        );

        // Mark step as complete
        $status->markStepComplete($step);

        // Update onboarding data
        $onboardingData = $status->onboarding_data ?? [];
        $onboardingData[$step] = $stepData;
        $status->update(['onboarding_data' => $onboardingData]);

        return ApiResponse::success([
            'status' => $status->fresh(),
            'step' => $step,
            'completed' => false,
        ]);
    }

    /**
     * Complete entire onboarding.
     */
    public function complete(Request $request): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'product_uuid' => ['required', 'uuid', 'exists:products,uuid'],
            'token' => ['required', 'string'],
        ]);

        $productUuid = $request->input('product_uuid');
        $token = $request->input('token');

        // Verify token
        $tokenModel = $this->tokenService->verify($token);
        if (! $tokenModel || $tokenModel->user_uuid !== $user->uuid || $tokenModel->product_uuid !== $productUuid) {
            return ApiResponse::errorUnauthorized('Invalid or expired token');
        }

        $product = Product::findOrFail($productUuid);

        // Get or create onboarding status
        $status = UserOnboardingStatus::firstOrCreate(
            [
                'user_uuid' => $user->uuid,
                'product_uuid' => $productUuid,
            ],
            [
                'completed_steps' => [],
                'onboarding_data' => [],
            ]
        );

        // For Memora, apply onboarding domain and brand name for all tiers
        if ($product->slug === 'memora') {
            $onboardingData = $status->onboarding_data ?? [];

            if (isset($onboardingData['domain']['domain'])) {
                $this->domainService->saveDomain($user, $onboardingData['domain']['domain']);
            }

            if (isset($onboardingData['branding']['name'])) {
                $this->settingsService->updateBranding(['name' => $onboardingData['branding']['name']]);
            }
        }

        // Mark as completed
        $status->markCompleted();

        return ApiResponse::success([
            'status' => $status->fresh(),
            'completed' => true,
        ]);
    }

    /**
     * Validate domain (Memora-specific).
     */
    public function validateDomain(ValidateDomainRequest $request): JsonResponse
    {
        $domain = $request->validated()['domain'];
        $validation = $this->domainService->validateDomain($domain);

        return ApiResponse::success($validation);
    }

    /**
     * Generate product selection token for new users.
     */
    public function generateProductSelectionToken(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Generate a secure token for product selection
        $token = bin2hex(random_bytes(32));

        // Store token in cache with 24 hour expiration
        \Illuminate\Support\Facades\Cache::put(
            "product_selection_token:{$token}",
            [
                'user_uuid' => $user->uuid,
                'created_at' => now()->toIso8601String(),
            ],
            now()->addHours(24)
        );

        return ApiResponse::success([
            'token' => $token,
            'expires_at' => now()->addHours(24)->toIso8601String(),
        ]);
    }
}
