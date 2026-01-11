<?php

namespace App\Services\CloudStorage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdobeService implements CloudStorageServiceInterface
{
    private string $clientId;

    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = config('services.adobe.client_id');
        $this->clientSecret = config('services.adobe.client_secret');
    }

    public function getAuthorizationUrl(string $state, string $redirectUri): string
    {
        $scopes = [
            'openid',
            'AdobeID',
            'creative_sdk',
            'cc_files',
        ];

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ];

        return 'https://ims-na1.adobelogin.com/ims/authorize/v2?'.http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post('https://ims-na1.adobelogin.com/ims/token/v3', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        if (! $response->successful()) {
            Log::error('Adobe token exchange failed', [
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange code for token');
        }

        return $response->json();
    }

    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://ims-na1.adobelogin.com/ims/token/v3', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        if (! $response->successful()) {
            throw new \Exception('Failed to refresh token');
        }

        return $response->json();
    }

    public function uploadFile(string $filePath, string $fileName, string $accessToken, ?string $folderName = null): string
    {
        // Adobe Creative Cloud Files API
        $fileContents = file_get_contents($filePath);

        // Create folder if needed
        $folderResponse = Http::withToken($accessToken)
            ->get('https://cc-api-storage.adobe.io/files');

        // Upload file
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/octet-stream',
                'x-api-key' => $this->clientId,
            ])
            ->put("https://cc-api-storage.adobe.io/files/{$fileName}", $fileContents);

        if (! $response->successful()) {
            Log::error('Adobe upload failed', [
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to upload file to Adobe Creative Cloud');
        }

        // Get file URL
        $fileResponse = Http::withToken($accessToken)
            ->withHeaders([
                'x-api-key' => $this->clientId,
            ])
            ->get("https://cc-api-storage.adobe.io/files/{$fileName}");

        if ($fileResponse->successful()) {
            return $fileResponse->json()['link'] ?? 'https://creative.adobe.com';
        }

        return 'https://creative.adobe.com';
    }

    public function supportsZipUpload(): bool
    {
        return true; // Adobe Creative Cloud supports ZIP files
    }

    public function uploadFiles(array $files, string $accessToken, string $albumName = 'Collection'): string
    {
        $firstFileUrl = null;

        foreach ($files as $file) {
            try {
                $fileContent = $file['content'] ?? file_get_contents($file['path']);
                $folder = $file['folder'] ?? 'Uncategorized';
                $filePath = $albumName.'/'.$folder.'/'.$file['name'];

                $response = Http::withToken($accessToken)
                    ->withHeaders([
                        'Content-Type' => 'application/octet-stream',
                        'x-api-key' => $this->clientId,
                    ])
                    ->put("https://cc-api-storage.adobe.io/files/{$filePath}", $fileContent);

                if ($response->successful() && ! $firstFileUrl) {
                    $fileResponse = Http::withToken($accessToken)
                        ->withHeaders(['x-api-key' => $this->clientId])
                        ->get("https://cc-api-storage.adobe.io/files/{$filePath}");
                    if ($fileResponse->successful()) {
                        $firstFileUrl = $fileResponse->json()['link'] ?? null;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to upload file to Adobe', [
                    'file' => $file['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $firstFileUrl ?? 'https://creative.adobe.com';
    }

    public function getServiceName(): string
    {
        return 'adobe';
    }
}
