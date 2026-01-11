<?php

namespace App\Services\CloudStorage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BoxService implements CloudStorageServiceInterface
{
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = config('services.box.client_id');
        $this->clientSecret = config('services.box.client_secret');
    }

    public function getAuthorizationUrl(string $state, string $redirectUri): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];

        return 'https://account.box.com/api/oauth2/authorize?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->withBasicAuth($this->clientId, $this->clientSecret)
            ->post('https://api.box.com/oauth2/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if (!$response->successful()) {
            Log::error('Box token exchange failed', [
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange code for token');
        }

        return $response->json();
    }

    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->withBasicAuth($this->clientId, $this->clientSecret)
            ->post('https://api.box.com/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to refresh token');
        }

        return $response->json();
    }

    public function uploadFile(string $filePath, string $fileName, string $accessToken, ?string $folderName = null): string
    {
        // Get root folder or create Mazeloot folder
        $folderResponse = Http::withToken($accessToken)
            ->get('https://api.box.com/2.0/folders/0/items', [
                'limit' => 1000,
            ]);

        $folderId = '0'; // Root folder
        if ($folderResponse->successful()) {
            $items = $folderResponse->json()['entries'] ?? [];
            foreach ($items as $item) {
                if ($item['type'] === 'folder' && $item['name'] === 'Mazeloot') {
                    $folderId = $item['id'];
                    break;
                }
            }

            // Create folder if it doesn't exist
            if ($folderId === '0') {
                $createFolderResponse = Http::withToken($accessToken)
                    ->post('https://api.box.com/2.0/folders', [
                        'name' => 'Mazeloot',
                        'parent' => ['id' => '0'],
                    ]);

                if ($createFolderResponse->successful()) {
                    $folderId = $createFolderResponse->json()['id'];
                }
            }
        }

        $fileContents = file_get_contents($filePath);
        $fileSize = filesize($filePath);

        // Upload file
        $attributes = json_encode([
            'name' => $fileName,
            'parent' => ['id' => $folderId],
        ]);

        $response = Http::withToken($accessToken)
            ->attach('attributes', $attributes, 'application/json')
            ->attach('file', $fileContents, $fileName)
            ->post('https://upload.box.com/api/2.0/files/content');

        if (!$response->successful()) {
            Log::error('Box upload failed', [
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to upload file to Box');
        }

        $fileData = $response->json()['entries'][0];
        $fileId = $fileData['id'];

        // Create shared link
        $shareResponse = Http::withToken($accessToken)
            ->put("https://api.box.com/2.0/files/{$fileId}", [
                'shared_link' => [
                    'access' => 'open',
                ],
            ]);

        if ($shareResponse->successful()) {
            return $shareResponse->json()['shared_link']['url'];
        }

        throw new \Exception('Failed to create shared link');
    }

    public function supportsZipUpload(): bool
    {
        return true; // Box supports ZIP files
    }

    public function uploadFiles(array $files, string $accessToken, string $albumName = 'Collection'): string
    {
        // Get or create Mazeloot folder
        $folderResponse = Http::withToken($accessToken)
            ->get('https://api.box.com/2.0/folders/0/items', ['limit' => 1000]);

        $mazelootFolderId = '0';
        if ($folderResponse->successful()) {
            $items = $folderResponse->json()['entries'] ?? [];
            foreach ($items as $item) {
                if ($item['type'] === 'folder' && $item['name'] === 'Mazeloot') {
                    $mazelootFolderId = $item['id'];
                    break;
                }
            }

            if ($mazelootFolderId === '0') {
                $createResponse = Http::withToken($accessToken)
                    ->post('https://api.box.com/2.0/folders', [
                        'name' => 'Mazeloot',
                        'parent' => ['id' => '0'],
                    ]);
                if ($createResponse->successful()) {
                    $mazelootFolderId = $createResponse->json()['id'];
                }
            }
        }

        // Create collection folder
        $collectionFolderResponse = Http::withToken($accessToken)
            ->post('https://api.box.com/2.0/folders', [
                'name' => $albumName,
                'parent' => ['id' => $mazelootFolderId],
            ]);

        $collectionFolderId = $mazelootFolderId;
        if ($collectionFolderResponse->successful()) {
            $collectionFolderId = $collectionFolderResponse->json()['id'];
        }

        $firstFileUrl = null;
        $currentSetFolderId = $collectionFolderId;

        foreach ($files as $file) {
            $folder = $file['folder'] ?? 'Uncategorized';
            
            // Create set folder if needed
            static $lastFolder = null;
            if ($lastFolder !== $folder) {
                $setFolderResponse = Http::withToken($accessToken)
                    ->post('https://api.box.com/2.0/folders', [
                        'name' => $folder,
                        'parent' => ['id' => $collectionFolderId],
                    ]);
                if ($setFolderResponse->successful()) {
                    $currentSetFolderId = $setFolderResponse->json()['id'];
                }
                $lastFolder = $folder;
            }

            try {
                $fileContent = $file['content'] ?? file_get_contents($file['path']);
                $attributes = json_encode([
                    'name' => $file['name'],
                    'parent' => ['id' => $currentSetFolderId],
                ]);

                $response = Http::withToken($accessToken)
                    ->attach('attributes', $attributes, 'application/json')
                    ->attach('file', $fileContent, $file['name'])
                    ->post('https://upload.box.com/api/2.0/files/content');

                if ($response->successful()) {
                    $fileData = $response->json()['entries'][0] ?? null;
                    if ($fileData && !$firstFileUrl) {
                        $shareResponse = Http::withToken($accessToken)
                            ->put("https://api.box.com/2.0/files/{$fileData['id']}", [
                                'shared_link' => ['access' => 'open'],
                            ]);
                        if ($shareResponse->successful()) {
                            $firstFileUrl = $shareResponse->json()['shared_link']['url'];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to upload file to Box', [
                    'file' => $file['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $firstFileUrl ?? "https://app.box.com/folder/{$collectionFolderId}";
    }

    public function getServiceName(): string
    {
        return 'box';
    }
}
