<?php

namespace App\Services\CloudStorage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DropboxService implements CloudStorageServiceInterface
{
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = config('services.dropbox.client_id');
        $this->clientSecret = config('services.dropbox.client_secret');
    }

    public function getAuthorizationUrl(string $state, string $redirectUri): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'state' => $state,
        ];

        return 'https://www.dropbox.com/oauth2/authorize?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->withBasicAuth($this->clientId, $this->clientSecret)
            ->post('https://api.dropboxapi.com/oauth2/token', [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ]);

        if (!$response->successful()) {
            Log::error('Dropbox token exchange failed', [
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange code for token');
        }

        return $response->json();
    }

    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->withBasicAuth($this->clientId, $this->clientSecret)
            ->post('https://api.dropboxapi.com/oauth2/token', [
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to refresh token');
        }

        return $response->json();
    }

    public function uploadFile(string $filePath, string $fileName, string $accessToken, ?string $folderName = null): string
    {
        $fileContents = file_get_contents($filePath);
        $path = '/Mazeloot/' . $fileName;

        // Upload file
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode([
                    'path' => $path,
                    'mode' => 'add',
                    'autorename' => true,
                ]),
                'Content-Type' => 'application/octet-stream',
            ])
            ->withBody($fileContents)
            ->post('https://content.dropboxapi.com/2/files/upload');

        if (!$response->successful()) {
            Log::error('Dropbox upload failed', [
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to upload file to Dropbox');
        }

        // Create shared link
        $createLinkResponse = Http::withToken($accessToken)
            ->post('https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings', [
                'path' => $path,
                'settings' => [
                    'requested_visibility' => 'public',
                ],
            ]);

        if (!$createLinkResponse->successful()) {
            // Try to get existing link
            $listResponse = Http::withToken($accessToken)
                ->post('https://api.dropboxapi.com/2/sharing/list_shared_links', [
                    'path' => $path,
                ]);

            if ($listResponse->successful() && !empty($listResponse->json()['links'])) {
                return $listResponse->json()['links'][0]['url'];
            }

            throw new \Exception('Failed to create shared link');
        }

        return $createLinkResponse->json()['url'];
    }

    public function supportsZipUpload(): bool
    {
        return true; // Dropbox supports ZIP files
    }

    public function uploadFiles(array $files, string $accessToken, string $albumName = 'Collection'): string
    {
        $basePath = '/Mazeloot/' . $albumName;
        $firstFileUrl = null;

        foreach ($files as $file) {
            $folder = $file['folder'] ?? 'Uncategorized';
            $path = $basePath . '/' . $folder . '/' . $file['name'];

            try {
                $fileContent = $file['content'] ?? file_get_contents($file['path']);

                $response = Http::withToken($accessToken)
                    ->withHeaders([
                        'Dropbox-API-Arg' => json_encode([
                            'path' => $path,
                            'mode' => 'add',
                            'autorename' => true,
                        ]),
                        'Content-Type' => 'application/octet-stream',
                    ])
                    ->withBody($fileContent)
                    ->post('https://content.dropboxapi.com/2/files/upload');

                if ($response->successful()) {
                    // Create shared link for first file
                    if (!$firstFileUrl) {
                        $linkResponse = Http::withToken($accessToken)
                            ->post('https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings', [
                                'path' => $basePath,
                                'settings' => ['requested_visibility' => 'public'],
                            ]);

                        if ($linkResponse->successful()) {
                            $firstFileUrl = $linkResponse->json()['url'];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to upload file to Dropbox', [
                    'file' => $file['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $firstFileUrl ?? "https://www.dropbox.com/home{$basePath}";
    }

    public function getServiceName(): string
    {
        return 'dropbox';
    }
}
