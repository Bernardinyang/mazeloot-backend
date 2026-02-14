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
use App\Http\Requests\V1\ChangePasswordRequest;
use App\Http\Requests\V1\DeleteAccountRequest;
use App\Http\Requests\V1\UpdateProfileRequest;
use App\Http\Requests\V1\VerifyMagicLinkRequest;
use App\Models\User;
use App\Models\Waitlist;
use App\Models\Notification;
use App\Notifications\AccountDeletedNotification;
use App\Notifications\AccountDeletionCodeNotification;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\MagicLinkService;
use App\Services\Auth\PasswordResetService;
use App\Services\ReferralService;
use App\Services\Storage\UserStorageService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
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
        protected ReferralService $referralService,
        protected UserStorageService $storageService
    ) {}

    /**
     * Login user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->recordFailedLogin($request->email, $request->ip());
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

        // Log login activity (password) — pass user as causer (not authenticated yet)
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'logged_in',
                $user,
                'User logged in',
                ['auth_method' => 'password'],
                $user,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log login activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $tierService = app(\App\Services\Subscription\TierService::class);

        return ApiResponse::successOk([
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'profile_photo' => $user->profile_photo,
                'email_verified_at' => $user->email_verified_at,
                'role' => $user->role->value,
                'memora_tier' => $user->memora_tier ?? 'starter',
                'memora_features' => $tierService->getFeatures($user),
                'memora_capabilities' => $tierService->getCapabilities($user),
                'set_limit_per_phase' => $tierService->getSetLimitPerPhase($user),
                'watermark_limit' => $tierService->getWatermarkLimit($user),
                'preset_limit' => $tierService->getPresetLimit($user),
                'selection_limit' => $tierService->getSelectionLimit($user),
                'proofing_limit' => $tierService->getProofingLimit($user),
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
        $referrer = $request->filled('referral_code')
            ? $this->referralService->resolveReferrerByCode($request->referral_code)
            : null;
        if ($referrer && $referrer->email === $request->email) {
            $referrer = null;
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'referred_by_user_uuid' => $referrer?->uuid,
        ]);

        if ($referrer) {
            \App\Models\ReferralInvite::firstOrCreate(
                [
                    'referrer_user_uuid' => $referrer->uuid,
                    'email' => $user->email,
                ],
                ['sent_at' => now()],
            );
        }

        // Send verification code
        $this->verificationService->sendVerificationCode($user);

        // Update waitlist status for ALL products if user was on waitlist
        // This handles cases where the same email joined waitlist for multiple products
        try {
            Waitlist::where('email', $user->email)
                ->where('status', 'not_registered')
                ->update([
                    'status' => 'registered',
                    'user_uuid' => $user->uuid,
                    'registered_at' => now(),
                ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update waitlist status', [
                'user_uuid' => $user->uuid ?? null,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        // Log registration activity (causer = user, not authenticated yet)
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'registered',
                $user,
                'User registered',
                null,
                $user,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log registration activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $tierService = app(\App\Services\Subscription\TierService::class);

        return ApiResponse::successCreated([
            'message' => 'Registration successful. Please verify your email.',
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email_verified_at' => $user->email_verified_at,
                'memora_tier' => $user->memora_tier ?? 'starter',
                'memora_features' => $tierService->getFeatures($user),
                'memora_capabilities' => $tierService->getCapabilities($user),
                'set_limit_per_phase' => $tierService->getSetLimitPerPhase($user),
                'watermark_limit' => $tierService->getWatermarkLimit($user),
                'preset_limit' => $tierService->getPresetLimit($user),
                'selection_limit' => $tierService->getSelectionLimit($user),
                'proofing_limit' => $tierService->getProofingLimit($user),
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

        // Log email verification activity (causer = user)
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'email_verified',
                $user,
                'Email verified',
                null,
                $user,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log email verification activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $tierService = app(\App\Services\Subscription\TierService::class);

        return ApiResponse::successOk([
            'message' => 'Email verified successfully.',
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email_verified_at' => $user->email_verified_at,
                'role' => $user->role->value,
                'memora_tier' => $user->memora_tier ?? 'starter',
                'memora_features' => $tierService->getFeatures($user),
                'memora_capabilities' => $tierService->getCapabilities($user),
                'set_limit_per_phase' => $tierService->getSetLimitPerPhase($user),
                'watermark_limit' => $tierService->getWatermarkLimit($user),
                'preset_limit' => $tierService->getPresetLimit($user),
                'selection_limit' => $tierService->getSelectionLimit($user),
                'proofing_limit' => $tierService->getProofingLimit($user),
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

        // Log verification code resend activity (causer = user)
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'verification_code_resent',
                $user,
                'Verification code resent',
                null,
                $user,
                $request
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

        // Log password reset requested activity (causer = user)
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'password_reset_requested',
                $user,
                'Password reset requested',
                null,
                $user,
                $request
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

        // Log password reset activity (causer = user)
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'password_reset',
                $user,
                'Password reset',
                null,
                $user,
                $request
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

                    // Update waitlist status for ALL products if user was on waitlist
                    // This handles cases where the same email joined waitlist for multiple products
                    try {
                        Waitlist::where('email', $user->email)
                            ->where('status', 'not_registered')
                            ->update([
                                'status' => 'registered',
                                'user_uuid' => $user->uuid,
                                'registered_at' => now(),
                            ]);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to update waitlist status (OAuth link)', [
                            'user_uuid' => $user->uuid ?? null,
                            'email' => $user->email,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    // Create new user from OAuth
                    $nameParts = $this->splitName($socialUser->getName());

                    $user = DB::transaction(function () use ($socialUser, $provider, $nameParts) {
                        $newUser = User::create([
                            'first_name' => $nameParts['first_name'],
                            'last_name' => $nameParts['last_name'],
                            'email' => $socialUser->getEmail(),
                            'provider' => $provider,
                            'provider_id' => $socialUser->getId(),
                            'email_verified_at' => now(), // OAuth emails are pre-verified
                            'password' => null, // No password for OAuth users
                            'profile_photo' => $socialUser->getAvatar(),
                        ]);

                        // Update waitlist status for ALL products if user was on waitlist
                        // This handles cases where the same email joined waitlist for multiple products
                        try {
                            Waitlist::where('email', $newUser->email)
                                ->where('status', 'not_registered')
                                ->update([
                                    'status' => 'registered',
                                    'user_uuid' => $newUser->uuid,
                                    'registered_at' => now(),
                                ]);
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to update waitlist status (OAuth)', [
                                'user_uuid' => $newUser->uuid ?? null,
                                'email' => $newUser->email,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        return $newUser;
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

            // Log OAuth login activity — pass user as causer (not authenticated yet)
            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'logged_in',
                    $user,
                    'User logged in via OAuth',
                    [
                        'auth_method' => 'oauth',
                        'provider' => $provider,
                    ],
                    $user,
                    request()
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

        // Log magic link sent activity (causer = user)
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'magic_link_sent',
                $user,
                'Magic link sent',
                null,
                $user,
                $request
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

        // Log magic link login activity — pass user as causer (not authenticated yet)
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'logged_in',
                $user,
                'User logged in via magic link',
                ['auth_method' => 'magic_link'],
                $user,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log magic link login activity', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $tierService = app(\App\Services\Subscription\TierService::class);

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
                'memora_tier' => $user->memora_tier ?? 'starter',
                'memora_features' => $tierService->getFeatures($user),
                'memora_capabilities' => $tierService->getCapabilities($user),
                'set_limit_per_phase' => $tierService->getSetLimitPerPhase($user),
                'watermark_limit' => $tierService->getWatermarkLimit($user),
                'preset_limit' => $tierService->getPresetLimit($user),
                'selection_limit' => $tierService->getSelectionLimit($user),
                'proofing_limit' => $tierService->getProofingLimit($user),
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

        $tierService = app(\App\Services\Subscription\TierService::class);

        return ApiResponse::successOk([
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'middle_name' => $user->middle_name,
                'profile_photo' => $user->profile_photo,
                'email_verified_at' => $user->email_verified_at,
                'has_password' => ! empty($user->password),
                'role' => $user->role->value,
                'memora_tier' => $user->memora_tier ?? 'starter',
                'memora_features' => $tierService->getFeatures($user),
                'memora_capabilities' => $tierService->getCapabilities($user),
                'set_limit_per_phase' => $tierService->getSetLimitPerPhase($user),
                'watermark_limit' => $tierService->getWatermarkLimit($user),
                'preset_limit' => $tierService->getPresetLimit($user),
                'selection_limit' => $tierService->getSelectionLimit($user),
                'proofing_limit' => $tierService->getProofingLimit($user),
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
     * Update authenticated user profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            return ApiResponse::errorUnauthorized('User not authenticated.');
        }

        $data = $request->validated();
        if (array_key_exists('first_name', $data)) {
            $user->first_name = $data['first_name'];
        }
        if (array_key_exists('last_name', $data)) {
            $user->last_name = $data['last_name'];
        }
        if (array_key_exists('middle_name', $data)) {
            $user->middle_name = $data['middle_name'];
        }
        if (array_key_exists('profile_photo', $data)) {
            $user->profile_photo = $data['profile_photo'];
        }
        $user->save();

        if (! $user->relationLoaded('status')) {
            $user->load('status');
        }
        if (! $user->relationLoaded('earlyAccess')) {
            $user->load('earlyAccess');
        }

        $tierService = app(\App\Services\Subscription\TierService::class);

        return ApiResponse::successOk([
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'middle_name' => $user->middle_name,
                'profile_photo' => $user->profile_photo,
                'email_verified_at' => $user->email_verified_at,
                'role' => $user->role->value,
                'memora_tier' => $user->memora_tier ?? 'starter',
                'memora_features' => $tierService->getFeatures($user),
                'memora_capabilities' => $tierService->getCapabilities($user),
                'set_limit_per_phase' => $tierService->getSetLimitPerPhase($user),
                'watermark_limit' => $tierService->getWatermarkLimit($user),
                'preset_limit' => $tierService->getPresetLimit($user),
                'selection_limit' => $tierService->getSelectionLimit($user),
                'proofing_limit' => $tierService->getProofingLimit($user),
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
     * Change password for authenticated user.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::errorUnauthorized('User not authenticated.');
        }

        $user->password = Hash::make($request->validated('password'));
        $user->save();

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'password_changed',
                $user,
                'User changed password',
                null,
                $user,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log password change', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successOk(['message' => 'Password updated successfully.']);
    }

    /**
     * Send account deletion confirmation code to authenticated user's email (for accounts without password).
     */
    public function sendDeletionCode(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::errorUnauthorized('User not authenticated.');
        }
        if ($user->password) {
            return ApiResponse::error('Use your password to delete your account.', 'HAS_PASSWORD', 400);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = 'account_deletion_code:'.$user->uuid;
        Cache::put($key, $code, now()->addMinutes(15));

        try {
            $user->notify(new AccountDeletionCodeNotification($code));
        } catch (\Throwable $e) {
            Cache::forget($key);
            \Illuminate\Support\Facades\Log::error('Failed to send account deletion code email', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to send code. Please try again.', 'EMAIL_FAILED', 500);
        }

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'account_deletion_code_sent',
                $user,
                'Account deletion confirmation code sent',
                null,
                $user,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log account deletion code sent', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successOk(['message' => 'Confirmation code sent to your email.']);
    }

    /**
     * Delete authenticated user account (soft delete).
     */
    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::errorUnauthorized('User not authenticated.');
        }

        $userEmail = $user->email;
        $userName = trim($user->first_name.' '.$user->last_name) ?: null;

        try {
            Notification::create([
                'user_uuid' => $user->uuid,
                'product' => 'general',
                'type' => 'account_deleted',
                'title' => 'Account deleted',
                'message' => 'Your Mazeloot account has been permanently deleted.',
                'description' => 'This confirms that your account and associated data have been removed.',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create account-deleted in-app notification', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            \Illuminate\Support\Facades\Notification::route('mail', $userEmail)
                ->notify(new AccountDeletedNotification($userName));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send account-deleted email', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $user->tokens()->delete();
        $user->delete();

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'account_deleted',
                $user,
                'User deleted account',
                null,
                $user,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log account deletion', [
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::successOk(['message' => 'Account deleted successfully.']);
    }

    /**
     * Logout: revoke current token and log activity.
     */
    public function logout(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'logged_out',
                    $user,
                    'User logged out',
                    null,
                    $user,
                    $request
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to log logout activity', [
                    'user_uuid' => $user->uuid ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
            $request->user()->currentAccessToken()->delete();
        }

        return ApiResponse::successOk(['message' => 'Logged out successfully']);
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
            $totalLimit = $quotaService->getUploadQuota(null, $user->uuid);

            // If no quota is set (unlimited), use 5GB as default for UI display
            if ($totalLimit === null) {
                $totalLimit = 5 * 1024 * 1024 * 1024; // 5GB default
            }

            $tierService = app(\App\Services\Subscription\TierService::class);
            $projectLimit = $tierService->getProjectLimit($user);
            $collectionLimit = $tierService->getCollectionLimit($user);
            $proofingLimit = $tierService->getProofingLimit($user);
            $selectionLimit = $tierService->getSelectionLimit($user);
            $rawFileLimit = $tierService->getRawFileLimit($user);
            $projectCount = \App\Domains\Memora\Models\MemoraProject::where('user_uuid', $user->uuid)->count();
            $collectionCount = \App\Domains\Memora\Models\MemoraCollection::where('user_uuid', $user->uuid)->count();
            $proofingCount = \App\Domains\Memora\Models\MemoraProofing::where('user_uuid', $user->uuid)->count();
            $selectionCount = \App\Domains\Memora\Models\MemoraSelection::where('user_uuid', $user->uuid)->count();
            $rawFileCount = \App\Domains\Memora\Models\MemoraRawFile::where('user_uuid', $user->uuid)->count();

            return ApiResponse::successOk([
                'total_used_bytes' => $totalUsed,
                'total_used_mb' => round($totalUsed / (1024 * 1024), 2),
                'total_used_gb' => round($totalUsed / (1024 * 1024 * 1024), 2),
                'total_storage_bytes' => $totalLimit,
                'total_storage_mb' => round($totalLimit / (1024 * 1024), 2),
                'total_storage_gb' => round($totalLimit / (1024 * 1024 * 1024), 2),
                'tier' => $tierService->getTier($user),
                'memora_features' => $tierService->getFeatures($user),
                'memora_capabilities' => $tierService->getCapabilities($user),
                'set_limit_per_phase' => $tierService->getSetLimitPerPhase($user),
                'selection_limit' => $selectionLimit,
                'selection_count' => $selectionCount,
                'proofing_limit' => $proofingLimit,
                'proofing_count' => $proofingCount,
                'project_count' => $projectCount,
                'project_limit' => $projectLimit,
                'collection_count' => $collectionCount,
                'collection_limit' => $collectionLimit,
                'raw_file_limit' => $rawFileLimit,
                'raw_file_count' => $rawFileCount,
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

    private function recordFailedLogin(string $identifier, ?string $ip): void
    {
        try {
            $entries = Cache::get('admin.failed_logins', []);
            array_unshift($entries, [
                'identifier' => $identifier,
                'ip' => $ip,
                'attempted_at' => now()->toIso8601String(),
            ]);
            Cache::put('admin.failed_logins', array_slice($entries, 0, 20), 86400);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
