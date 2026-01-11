<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Controllers\Controller;
use App\Services\CloudStorage\CloudStorageFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CloudStorageOAuthController extends Controller
{
    public function initiate(Request $request)
    {
        $request->validate([
            'service' => 'required|string|in:googledrive,google,dropbox,onedrive,box,adobe',
            'collection_id' => 'required|string',
            'project_id' => 'nullable|string',
            'destination' => 'required|string',
            'download_token' => 'nullable|string',
        ]);

        $service = $request->input('service');
        $collectionId = $request->input('collection_id');
        $projectId = $request->input('project_id');
        $destination = $request->input('destination');
        $downloadToken = $request->input('download_token');

        try {
            $cloudService = CloudStorageFactory::make($service);
            $redirectUri = $this->getRedirectUri($service);

            // Generate state token
            $state = bin2hex(random_bytes(32));
            
            // Store state in cache with metadata
            Cache::put("cloud_oauth_state_{$state}", [
                'service' => $service,
                'collection_id' => $collectionId,
                'project_id' => $projectId,
                'destination' => $destination,
                'download_token' => $downloadToken,
                'redirect_uri' => $redirectUri,
                'created_at' => now(),
            ], now()->addMinutes(10));

            $authUrl = $cloudService->getAuthorizationUrl($state, $redirectUri);

            Log::info('OAuth initiation', [
                'service' => $service,
                'redirect_uri' => $redirectUri,
                'auth_url' => $authUrl,
            ]);

            return response()->json([
                'success' => true,
                'auth_url' => $authUrl,
                'state' => $state,
                'requires_oauth' => true,
                'redirect_uri' => $redirectUri, // Include for debugging
            ]);
        } catch (\Exception $e) {
            Log::error('Cloud storage OAuth initiation failed', [
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate OAuth flow',
            ], 500);
        }
    }

    public function callback(Request $request, string $service)
    {
        $code = $request->input('code');
        $state = $request->input('state');
        $error = $request->input('error');
        $errorDescription = $request->input('error_description');

        if ($error) {
            Log::error('OAuth callback error', [
                'service' => $service,
                'error' => $error,
                'error_description' => $errorDescription,
            ]);

            // Provide user-friendly error messages
            $userMessage = $this->getUserFriendlyErrorMessage($error, $errorDescription, $service);

            return redirect($this->getFrontendRedirectUrl([
                'success' => false,
                'error' => $userMessage,
                'error_code' => $error,
                'service' => $service,
            ]));
        }

        if (!$code || !$state) {
            return redirect($this->getFrontendRedirectUrl([
                'success' => false,
                'error' => 'Missing code or state',
                'service' => $service,
            ]));
        }

        // Retrieve state from cache for OAuth services
        $stateData = Cache::get("cloud_oauth_state_{$state}");
        if (!$stateData) {
            return redirect($this->getFrontendRedirectUrl([
                'success' => false,
                'error' => 'Invalid or expired state',
                'service' => $service,
            ]));
        }

        try {
            $cloudService = CloudStorageFactory::make($service);
            $redirectUri = $stateData['redirect_uri'];

            // Exchange code for token
            $tokenData = $cloudService->exchangeCodeForToken($code, $redirectUri);

            // Store token in cache with download token or collection ID as key
            $tokenKey = $stateData['download_token'] 
                ? "cloud_token_{$service}_{$stateData['download_token']}"
                : "cloud_token_{$service}_{$stateData['collection_id']}";


            Cache::put($tokenKey, [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_in' => $tokenData['expires_in'] ?? 3600,
                'token_type' => $tokenData['token_type'] ?? 'Bearer',
                'created_at' => now(),
            ], now()->addHours(24));

            // Clean up state
            Cache::forget("cloud_oauth_state_{$state}");

            return redirect($this->getFrontendRedirectUrl([
                'success' => true,
                'service' => $service,
                'download_token' => $stateData['download_token'],
                'collection_id' => $stateData['collection_id'],
                'project_id' => $stateData['project_id'] ?? null,
                'destination' => $stateData['destination'],
            ]));
        } catch (\Exception $e) {
            Log::error('OAuth callback processing failed', [
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            return redirect($this->getFrontendRedirectUrl([
                'success' => false,
                'error' => $e->getMessage(),
                'service' => $service,
            ]));
        }
    }

    private function getRedirectUri(string $service): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $redirectUri = "{$baseUrl}/api/v1/cloud-storage/oauth/{$service}/callback";
        
        // Log for debugging
        Log::info('OAuth redirect URI generated', [
            'service' => $service,
            'redirect_uri' => $redirectUri,
            'base_url' => $baseUrl,
        ]);
        
        return $redirectUri;
    }

    private function getFrontendRedirectUrl(array $params): string
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $query = http_build_query($params);
        return "{$frontendUrl}/cloud-storage/oauth/callback?{$query}";
    }

    private function getUserFriendlyErrorMessage(string $error, ?string $errorDescription, string $service): string
    {
        $serviceName = match ($service) {
            'googledrive' => 'Google Drive',
            'google' => 'Google Photos',
            'dropbox' => 'Dropbox',
            'onedrive' => 'OneDrive',
            'box' => 'Box',
            'adobe' => 'Adobe Creative Cloud',
            default => ucfirst($service),
        };

        return match ($error) {
            'access_denied' => "Access denied. Please ensure the app is properly configured in {$serviceName} and your account has been added as a test user if the app is in testing mode.",
            'invalid_client' => "Invalid client configuration. Please check your {$serviceName} OAuth credentials.",
            'invalid_grant' => "Invalid authorization code. Please try again.",
            'invalid_request' => "Invalid request. Please check your OAuth configuration.",
            'invalid_scope' => "Invalid scope requested. Please check your {$serviceName} OAuth scopes configuration.",
            'server_error' => "{$serviceName} server error. Please try again later.",
            'temporarily_unavailable' => "{$serviceName} is temporarily unavailable. Please try again later.",
            default => $errorDescription ?? "OAuth error: {$error}. Please contact support if this persists.",
        };
    }
}
