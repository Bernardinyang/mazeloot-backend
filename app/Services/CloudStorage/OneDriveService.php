<?php

namespace App\Services\CloudStorage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneDriveService implements CloudStorageServiceInterface
{
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = config('services.onedrive.client_id');
        $this->clientSecret = config('services.onedrive.client_secret');
    }

    public function getAuthorizationUrl(string $state, string $redirectUri): string
    {
        $scopes = [
            'files.readwrite',
            'offline_access',
        ];

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'response_mode' => 'query',
            'state' => $state,
        ];

        return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if (!$response->successful()) {
            Log::error('OneDrive token exchange failed', [
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange code for token');
        }

        return $response->json();
    }

    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
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
        $fileSize = filesize($filePath);

        // Use simple upload for files < 4MB, otherwise use session upload
        if ($fileSize < 4 * 1024 * 1024) {
            $response = Http::withToken($accessToken)
                ->put("https://graph.microsoft.com/v1.0/me/drive/root:/Mazeloot/{$fileName}:/content", $fileContents);

            if (!$response->successful()) {
                Log::error('OneDrive upload failed', [
                    'response' => $response->body(),
                ]);
                throw new \Exception('Failed to upload file to OneDrive');
            }

            $fileData = $response->json();
            $itemId = $fileData['id'];
        } else {
            // For large files, create upload session
            $sessionResponse = Http::withToken($accessToken)
                ->post("https://graph.microsoft.com/v1.0/me/drive/root:/Mazeloot/{$fileName}:/createUploadSession", [
                    'item' => [
                        '@microsoft.graph.conflictBehavior' => 'rename',
                    ],
                ]);

            if (!$sessionResponse->successful()) {
                throw new \Exception('Failed to create upload session');
            }

            $uploadUrl = $sessionResponse->json()['uploadUrl'];

            // Upload file in chunks
            $chunkSize = 320 * 1024; // 320 KB chunks
            $offset = 0;

            while ($offset < $fileSize) {
                $chunk = substr($fileContents, $offset, $chunkSize);
                $rangeEnd = min($offset + $chunkSize - 1, $fileSize - 1);

                $chunkResponse = Http::withHeaders([
                    'Content-Length' => strlen($chunk),
                    'Content-Range' => "bytes {$offset}-{$rangeEnd}/{$fileSize}",
                ])->put($uploadUrl, $chunk);

                if (!$chunkResponse->successful()) {
                    throw new \Exception('Failed to upload chunk');
                }

                $offset += $chunkSize;

                if ($chunkResponse->json() && isset($chunkResponse->json()['id'])) {
                    $itemId = $chunkResponse->json()['id'];
                    break;
                }
            }
        }

        // Create sharing link
        $shareResponse = Http::withToken($accessToken)
            ->post("https://graph.microsoft.com/v1.0/me/drive/items/{$itemId}/createLink", [
                'type' => 'view',
                'scope' => 'anonymous',
            ]);

        if ($shareResponse->successful()) {
            return $shareResponse->json()['link']['webUrl'];
        }

        // Fallback to file URL
        $fileResponse = Http::withToken($accessToken)
            ->get("https://graph.microsoft.com/v1.0/me/drive/items/{$itemId}");

        if ($fileResponse->successful()) {
            return $fileResponse->json()['webUrl'];
        }

        throw new \Exception('Failed to get file URL');
    }

    public function supportsZipUpload(): bool
    {
        return true; // OneDrive supports ZIP files
    }

    public function uploadFiles(array $files, string $accessToken, string $albumName = 'Collection'): string
    {
        $basePath = 'Mazeloot/' . $albumName;
        $firstFileUrl = null;

        foreach ($files as $file) {
            $folder = $file['folder'] ?? 'Uncategorized';
            $filePath = $basePath . '/' . $folder . '/' . $file['name'];

            try {
                $fileContent = $file['content'] ?? file_get_contents($file['path']);
                $fileSize = strlen($fileContent);

                if ($fileSize < 4 * 1024 * 1024) {
                    $response = Http::withToken($accessToken)
                        ->put("https://graph.microsoft.com/v1.0/me/drive/root:/{$filePath}:/content", $fileContent);

                    if ($response->successful()) {
                        $fileData = $response->json();
                        if (!$firstFileUrl) {
                            $shareResponse = Http::withToken($accessToken)
                                ->post("https://graph.microsoft.com/v1.0/me/drive/items/{$fileData['id']}/createLink", [
                                    'type' => 'view',
                                    'scope' => 'anonymous',
                                ]);
                            if ($shareResponse->successful()) {
                                $firstFileUrl = $shareResponse->json()['link']['webUrl'];
                            }
                        }
                    }
                } else {
                    // Large file - use upload session (simplified)
                    Log::warning('Large file skipped in multi-file upload', ['file' => $file['name']]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to upload file to OneDrive', [
                    'file' => $file['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $firstFileUrl ?? "https://onedrive.live.com/?id=root&cid={$basePath}";
    }

    public function getServiceName(): string
    {
        return 'onedrive';
    }
}
