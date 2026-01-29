<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ForgotPasswordRequest;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RegisterRequest;
use App\Http\Requests\V1\ResendVerificationRequest;
use App\Http\Requests\V1\ResetPasswordRequest;
use App\Http\Requests\V1\SendMagicLinkRequest;
use App\Http\Requests\V1\VerifyEmailRequest;
use App\Http\Requests\V1\VerifyMagicLinkRequest;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\MagicLinkService;
use App\Services\Auth\PasswordResetService;
use App\Services\Storage\UserStorageService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        protected EmailVerificationService $verificationService,
        protected PasswordResetService $passwordResetService,
        protected MagicLinkService $magicLinkService,
        protected UserStorageService $storageService
    ) {}

    /**
     * Login user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if email is verified
        if (! $user->email_verified_at) {
            return ApiResponse::error(
                'Please verify your email address before logging in.',
                'EMAIL_NOT_VERIFIED',
                403
            );
        }

        // Check user status
        $canLogin = $user->canLogin();
        if (! $canLogin['can_login']) {
            return ApiResponse::error(
                $canLogin['message'],
                'ACCOUNT_STATUS_BLOCKED',
                403
            );
        }

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Load status relationship if not already loaded
        if (! $user->relationLoaded('status')) {
            $user->load('status');
        }

        // Log login activity (password)
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'logged_in',
                $user,
                'User logged in',
                ['auth_method' => 'password']
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log login activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successOk([
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'profile_photo' => $user->profile_photo,
                'email_verified_at' => $user->email_verified_at,
                'role' => $user->role->value,
                'status' => $user->status ? [
                    'uuid' => $user->status->uuid,
                    'name' => $user->status->name,
                    'description' => $user->status->description,
                    'color' => $user->status->color,
                ] : null,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Send verification code
        $this->verificationService->sendVerificationCode($user);

        // Log registration activity
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'registered',
                $user,
                'User registered'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log registration activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successCreated([
            'message' => 'Registration successful. Please verify your email.',
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email_verified_at' => $user->email_verified_at,
            ],
            'requires_verification' => true,
        ]);
    }

    /**
     * Verify email with code.
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return ApiResponse::errorNotFound('User not found.');
        }

        if ($user->email_verified_at) {
            return ApiResponse::error('Email is already verified.', 'EMAIL_ALREADY_VERIFIED', 400);
        }

        $verified = $this->verificationService->verifyCode($user, $request->code);

        if (! $verified) {
            return ApiResponse::error('Invalid or expired verification code.', 'INVALID_CODE', 400);
        }

        // Refresh user to get updated email_verified_at
        $user->refresh();

        // Create token for login
        $token = $user->createToken('auth-token')->plainTextToken;

        // Log email verification activity
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'email_verified',
                $user,
                'Email verified'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log email verification activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successOk([
            'message' => 'Email verified successfully.',
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email_verified_at' => $user->email_verified_at,
                'role' => $user->role->value,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Resend verification code.
     */
    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return ApiResponse::errorNotFound('User not found.');
        }

        if ($user->email_verified_at) {
            return ApiResponse::error('Email is already verified.', 'EMAIL_ALREADY_VERIFIED', 400);
        }

        $this->verificationService->sendVerificationCode($user);

        // Log verification code resend activity
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'verification_code_resent',
                $user,
                'Verification code resent'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log verification resend activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successOk([
            'message' => 'Verification code sent successfully.',
        ]);
    }

    /**
     * Send password reset code.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            // Don't reveal if user exists for security
            return ApiResponse::successOk([
                'message' => 'If the email exists, a password reset code has been sent.',
            ]);
        }

        $this->passwordResetService->sendResetCode($user);

        // Log password reset requested activity
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'password_reset_requested',
                $user,
                'Password reset requested'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log password reset request activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successOk([
            'message' => 'If the email exists, a password reset code has been sent.',
        ]);
    }

    /**
     * Verify password reset code.
     */
    public function verifyResetCode(\App\Http\Requests\V1\VerifyResetCodeRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return ApiResponse::errorNotFound('User not found.');
        }

        $resetToken = $this->passwordResetService->verifyCode($user, $request->code);

        if (! $resetToken) {
            return ApiResponse::error('Invalid or expired reset code.', 'INVALID_CODE', 400);
        }

        return ApiResponse::successOk([
            'message' => 'Reset code verified successfully.',
        ]);
    }

    /**
     * Reset password with code.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return ApiResponse::errorNotFound('User not found.');
        }

        $reset = $this->passwordResetService->resetPassword(
            $user,
            $request->code,
            $request->password
        );

        if (! $reset) {
            return ApiResponse::error('Invalid or expired reset code.', 'INVALID_CODE', 400);
        }

        // Log password reset activity
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'password_reset',
                $user,
                'Password reset'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log password reset activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successOk([
            'message' => 'Password reset successfully. You can now login with your new password.',
        ]);
    }

    /**
     * Redirect user to OAuth provider (Google).
     * Returns the redirect URL for the frontend to use.
     */
    public function redirectToProvider(string $provider): JsonResponse
    {
        // Validate provider
        if (! in_array($provider, ['google'])) {
            return ApiResponse::error('Invalid OAuth provider.', 'INVALID_PROVIDER', 400);
        }

        try {
            $redirectUrl = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();

            return ApiResponse::successOk([
                'redirect_url' => $redirectUrl,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('OAuth redirect failed: '.$e->getMessage(), 'OAUTH_ERROR', 400);
        }
    }

    /**
     * Handle OAuth provider callback.
     * Redirects to frontend callback page with token in URL hash for security.
     */
    public function handleProviderCallback(string $provider): \Illuminate\Http\RedirectResponse
    {
        // Validate provider
        if (! in_array($provider, ['google'])) {
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));

            return redirect($frontendUrl.'/auth/oauth/callback?error=invalid_provider&message=Invalid OAuth provider');
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Check if user exists by provider_id or email
            $user = User::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if (! $user) {
                // Check if user exists by email (linking OAuth to existing account)
                $user = User::where('email', $socialUser->getEmail())->first();

                if ($user) {
                    // Link OAuth provider to existing account
                    $user->update([
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                    ]);
                } else {
                    // Create new user from OAuth
                    $nameParts = $this->splitName($socialUser->getName());

                    $user = DB::transaction(function () use ($socialUser, $provider, $nameParts) {
                        return User::create([
                            'first_name' => $nameParts['first_name'],
                            'last_name' => $nameParts['last_name'],
                            'email' => $socialUser->getEmail(),
                            'provider' => $provider,
                            'provider_id' => $socialUser->getId(),
                            'email_verified_at' => now(), // OAuth emails are pre-verified
                            'password' => null, // No password for OAuth users
                            'profile_photo' => $socialUser->getAvatar(),
                        ]);
                    });
                }
            } else {
                // Update user info from OAuth provider
                $user->update([
                    'profile_photo' => $socialUser->getAvatar() ?: $user->profile_photo,
                ]);
            }

            // Check user status before allowing login
            $canLogin = $user->canLogin();
            if (! $canLogin['can_login']) {
                // Redirect to frontend callback page with error
                $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
                $callbackUrl = $frontendUrl.'/auth/oauth/callback?'.http_build_query([
                    'error' => 'account_blocked',
                    'message' => $canLogin['message'],
                ]);

                return redirect($callbackUrl);
            }

            // Create token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Log OAuth login activity
            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'logged_in',
                    $user,
                    'User logged in via OAuth',
                    [
                        'auth_method' => 'oauth',
                        'provider' => $provider,
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to log OAuth login activity', [
                    'user_uuid' => $user->uuid ?? null,
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ]);
            }

            // Load status relationship if not already loaded
            if (! $user->relationLoaded('status')) {
                $user->load('status');
            }

            // Redirect to frontend callback page with token in URL hash
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            $callbackUrl = $frontendUrl.'/auth/oauth/callback#'.http_build_query([
                'token' => $token,
                'success' => 'true',
            ]);

            return redirect($callbackUrl);
        } catch (\Exception $e) {
            // Redirect to frontend callback page with error
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            $callbackUrl = $frontendUrl.'/auth/oauth/callback?'.http_build_query([
                'error' => 'oauth_error',
                'message' => $e->getMessage(),
            ]);

            return redirect($callbackUrl);
        }
    }

    /**
     * Send magic link to user email.
     */
    public function sendMagicLink(SendMagicLinkRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            // Don't reveal if user exists for security
            return ApiResponse::successOk([
                'message' => 'If the email exists, a magic link has been sent.',
            ]);
        }

        $this->magicLinkService->sendMagicLink($user);

        // Log magic link sent activity
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'magic_link_sent',
                $user,
                'Magic link sent'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log magic link sent activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successOk([
            'message' => 'If the email exists, a magic link has been sent.',
        ]);
    }

    /**
     * Verify magic link and login user.
     */
    public function verifyMagicLink(VerifyMagicLinkRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return ApiResponse::errorNotFound('User not found.');
        }

        $magicLinkToken = $this->magicLinkService->verifyToken($request->token, $request->email);

        if (! $magicLinkToken) {
            return ApiResponse::error('Invalid or expired magic link.', 'INVALID_MAGIC_LINK', 400);
        }

        // Check user status before allowing login
        $canLogin = $user->canLogin();
        if (! $canLogin['can_login']) {
            return ApiResponse::error(
                $canLogin['message'],
                'ACCOUNT_STATUS_BLOCKED',
                403
            );
        }

        // Mark token as used
        $magicLinkToken->markAsUsed();

        // Create token for login
        $token = $user->createToken('auth-token')->plainTextToken;

        // Load status relationship if not already loaded
        if (! $user->relationLoaded('status')) {
            $user->load('status');
        }

        // Log magic link login activity
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'logged_in',
                $user,
                'User logged in via magic link',
                ['auth_method' => 'magic_link']
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log magic link login activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successOk([
            'message' => 'Magic link verified successfully.',
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'profile_photo' => $user->profile_photo,
                'email_verified_at' => $user->email_verified_at,
                'role' => $user->role->value,
                'status' => $user->status ? [
                    'uuid' => $user->status->uuid,
                    'name' => $user->status->name,
                    'description' => $user->status->description,
                    'color' => $user->status->color,
                ] : null,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function user(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return ApiResponse::errorUnauthorized('User not authenticated.');
        }

        // Load relationships if not already loaded
        if (! $user->relationLoaded('status')) {
            $user->load('status');
        }
        if (! $user->relationLoaded('earlyAccess')) {
            $user->load('earlyAccess');
        }

        return ApiResponse::successOk([
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'profile_photo' => $user->profile_photo,
                'email_verified_at' => $user->email_verified_at,
                'role' => $user->role->value,
                'status' => $user->status ? [
                    'uuid' => $user->status->uuid,
                    'name' => $user->status->name,
                    'description' => $user->status->description,
                    'color' => $user->status->color,
                ] : null,
                'early_access' => $user->earlyAccess && $user->earlyAccess->isActive() ? [
                    'is_active' => true,
                    'discount_percentage' => $user->earlyAccess->discount_percentage,
                    'discount_rules' => $user->earlyAccess->discount_rules,
                    'feature_flags' => $user->earlyAccess->feature_flags ?? [],
                    'storage_multiplier' => $user->earlyAccess->storage_multiplier,
                    'priority_support' => $user->earlyAccess->priority_support,
                    'exclusive_badge' => $user->earlyAccess->exclusive_badge,
                    'trial_extension_days' => $user->earlyAccess->trial_extension_days,
                    'custom_branding_enabled' => $user->earlyAccess->custom_branding_enabled,
                    'release_version' => $user->earlyAccess->release_version,
                    'expires_at' => $user->earlyAccess->expires_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    /**
     * Get storage usage for authenticated user.
     */
    public function storage(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (! $user) {
                return ApiResponse::errorUnauthorized('User not authenticated.');
            }

            // Get cached storage (fast) - only check actual cloud storage if explicitly enabled
            // Default to false for performance - cache is maintained on upload/delete
            $checkActual = config('storage.check_actual_sizes', false);

            $totalUsed = $this->storageService->getTotalStorageUsed($user->uuid, $checkActual);

            // Get storage quota/limit from config (default 500MB, but can be overridden per user)
            // If no quota is set, default to 5GB for display purposes
            $quotaService = app(\App\Services\Quotas\QuotaService::class);
            $totalLimit = $quotaService->getUploadQuota(null, $user->id);

            // If no quota is set (unlimited), use 5GB as default for UI display
            if ($totalLimit === null) {
                $totalLimit = 5 * 1024 * 1024 * 1024; // 5GB default
            }

            return ApiResponse::successOk([
                'total_used_bytes' => $totalUsed,
                'total_used_mb' => round($totalUsed / (1024 * 1024), 2),
                'total_used_gb' => round($totalUsed / (1024 * 1024 * 1024), 2),
                'total_storage_bytes' => $totalLimit,
                'total_storage_mb' => round($totalLimit / (1024 * 1024), 2),
                'total_storage_gb' => round($totalLimit / (1024 * 1024 * 1024), 2),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to get storage usage', [
                'user_uuid' => $user->uuid ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::errorInternalServerError('Failed to retrieve storage usage. Please try again later.');
        }
    }

    /**
     * Split full name into first and last name.
     */
    private function splitName(?string $fullName): array
    {
        if (! $fullName) {
            return ['first_name' => 'User', 'last_name' => ''];
        }

        $parts = explode(' ', trim($fullName), 2);

        return [
            'first_name' => $parts[0] ?: 'User',
            'last_name' => $parts[1] ?? '',
        ];
    }
}
