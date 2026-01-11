<?php

namespace App\Services\CloudStorage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleDriveService implements CloudStorageServiceInterface
{
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
    }

    public function getAuthorizationUrl(string $state, string $redirectUri): string
    {
        $scopes = [
            'https://www.googleapis.com/auth/drive.file',
        ];

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if (!$response->successful()) {
            Log::error('Google Drive token exchange failed', [
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange code for token');
        }

        return $response->json();
    }

    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
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

    public function uploadFile(string $filePath, string $fileName, string $accessToken, string $folderName = null): string
    {
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/zip';
        $fileContents = file_get_contents($filePath);

        $folderId = null;
        
        // Create folder if folderName is provided
        if ($folderName) {
            $folderMetadata = [
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ];

            $folderResponse = Http::withToken($accessToken)
                ->post('https://www.googleapis.com/drive/v3/files', $folderMetadata);

            if ($folderResponse->successful()) {
                $folderData = $folderResponse->json();
                $folderId = $folderData['id'] ?? null;
                Log::info('Google Drive folder created', [
                    'folder_id' => $folderId,
                    'folder_name' => $folderName,
                ]);
            } else {
                Log::warning('Failed to create Google Drive folder, uploading to root', [
                    'response' => $folderResponse->body(),
                    'folder_name' => $folderName,
                ]);
            }
        }

        // Create metadata with parent folder if created
        $metadata = [
            'name' => $fileName,
        ];
        
        if ($folderId) {
            $metadata['parents'] = [$folderId];
        }

        // Build multipart body manually for Google Drive API
        $boundary = '----WebKitFormBoundary' . uniqid();
        $delimiter = "\r\n--{$boundary}\r\n";
        $closeDelimiter = "\r\n--{$boundary}--\r\n";

        $body = '';
        $body .= $delimiter;
        $body .= 'Content-Type: application/json; charset=UTF-8' . "\r\n\r\n";
        $body .= json_encode($metadata);
        $body .= $delimiter;
        $body .= 'Content-Type: ' . $mimeType . "\r\n";
        $body .= 'Content-Transfer-Encoding: binary' . "\r\n\r\n";
        $body .= $fileContents;
        $body .= $closeDelimiter;

        // Upload file using multipart upload
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'multipart/related; boundary=' . $boundary,
            ])
            ->withBody($body)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');

        if (!$response->successful()) {
            Log::error('Google Drive upload failed', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);
            throw new \Exception('Failed to upload file to Google Drive: ' . $response->body());
        }

        $fileData = $response->json();
        
        if (!isset($fileData['id'])) {
            throw new \Exception('Failed to get file ID from Google Drive response');
        }

        $fileId = $fileData['id'];
        
        // Make file shareable (non-blocking - if it fails, still return the file URL)
        try {
            Http::withToken($accessToken)
                ->post("https://www.googleapis.com/drive/v3/files/{$fileId}/permissions", [
                    'role' => 'reader',
                    'type' => 'anyone',
                ]);
        } catch (\Exception $e) {
            Log::warning('Failed to make Google Drive file shareable', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
        }

        // Make folder shareable if created
        if ($folderId) {
            try {
                Http::withToken($accessToken)
                    ->post("https://www.googleapis.com/drive/v3/files/{$folderId}/permissions", [
                        'role' => 'reader',
                        'type' => 'anyone',
                    ]);
            } catch (\Exception $e) {
                Log::warning('Failed to make Google Drive folder shareable', [
                    'folder_id' => $folderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Return folder URL if folder was created, otherwise file URL
        if ($folderId) {
            $folderUrl = "https://drive.google.com/drive/folders/{$folderId}";
            Log::info('Google Drive upload completed (with folder)', [
                'folder_id' => $folderId,
                'folder_url' => $folderUrl,
                'file_id' => $fileId,
                'file_name' => $fileName,
            ]);
            return $folderUrl;
        }
        
        $fileUrl = "https://drive.google.com/file/d/{$fileId}/view";
        Log::info('Google Drive upload completed', [
            'file_id' => $fileId,
            'file_url' => $fileUrl,
            'file_name' => $fileName,
        ]);
        return $fileUrl;
    }

    public function supportsZipUpload(): bool
    {
        return true; // Google Drive supports ZIP files
    }

    public function uploadFiles(array $files, string $accessToken, string $albumName = 'Collection'): string
    {
        // Create folder for collection
        $folderMetadata = [
            'name' => $albumName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ];

        $folderResponse = Http::withToken($accessToken)
            ->post('https://www.googleapis.com/drive/v3/files', $folderMetadata);

        $folderId = null;
        if ($folderResponse->successful()) {
            $folderData = $folderResponse->json();
            $folderId = $folderData['id'] ?? null;
        }

        // Upload files organized by set (folder)
        $currentFolderId = $folderId;
        $firstFileUrl = null;

        foreach ($files as $file) {
            $folder = $file['folder'] ?? 'Uncategorized';
            
            // Create set folder if needed (if folder changed)
            static $lastFolder = null;
            if ($lastFolder !== $folder) {
                $setFolderMetadata = [
                    'name' => $folder,
                    'mimeType' => 'application/vnd.google-apps.folder',
                ];
                if ($folderId) {
                    $setFolderMetadata['parents'] = [$folderId];
                }

                $setFolderResponse = Http::withToken($accessToken)
                    ->post('https://www.googleapis.com/drive/v3/files', $setFolderMetadata);

                if ($setFolderResponse->successful()) {
                    $setFolderData = $setFolderResponse->json();
                    $currentFolderId = $setFolderData['id'] ?? $folderId;
                }
                $lastFolder = $folder;
            }

            // Upload file
            try {
                $fileContent = $file['content'] ?? file_get_contents($file['path']);
                $path = $file['path'] ?? '';
                
                // Detect MIME type
                $mimeType = 'application/octet-stream';
                if ($path && file_exists($path)) {
                    $mimeType = mime_content_type($path) ?: 'application/octet-stream';
                } elseif (isset($file['mime_type'])) {
                    $mimeType = $file['mime_type'];
                } else {
                    // Try to detect from extension
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $mimeTypes = [
                        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                        'gif' => 'image/gif', 'webp' => 'image/webp', 'mp4' => 'video/mp4',
                        'mov' => 'video/quicktime', 'pdf' => 'application/pdf',
                    ];
                    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
                }

                $metadata = [
                    'name' => $file['name'],
                ];
                if ($currentFolderId) {
                    $metadata['parents'] = [$currentFolderId];
                }

                // Build multipart body for Google Drive API
                $boundary = '----WebKitFormBoundary' . uniqid();
                $delimiter = "\r\n--{$boundary}\r\n";
                $closeDelimiter = "\r\n--{$boundary}--\r\n";

                $body = '';
                $body .= $delimiter;
                $body .= 'Content-Type: application/json; charset=UTF-8' . "\r\n\r\n";
                $body .= json_encode($metadata);
                $body .= $delimiter;
                $body .= 'Content-Type: ' . $mimeType . "\r\n";
                $body .= 'Content-Transfer-Encoding: binary' . "\r\n\r\n";
                $body .= $fileContent;
                $body .= $closeDelimiter;

                $response = Http::withToken($accessToken)
                    ->withHeaders([
                        'Content-Type' => 'multipart/related; boundary=' . $boundary,
                    ])
                    ->withBody($body)
                    ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');

                if ($response->successful()) {
                    $fileData = $response->json();
                    if (!$firstFileUrl && isset($fileData['id'])) {
                        $firstFileUrl = "https://drive.google.com/file/d/{$fileData['id']}/view";
                    }
                } else {
                    Log::warning('Failed to upload file to Google Drive', [
                        'file' => $file['name'] ?? 'unknown',
                        'response' => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to upload file to Google Drive', [
                    'file' => $file['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Return folder URL or first file URL
        $finalUrl = null;
        if ($folderId) {
            $finalUrl = "https://drive.google.com/drive/folders/{$folderId}";
        } elseif ($firstFileUrl) {
            $finalUrl = $firstFileUrl;
        } else {
            $finalUrl = 'https://drive.google.com';
        }
        
        Log::info('Google Drive multi-file upload completed', [
            'folder_id' => $folderId,
            'final_url' => $finalUrl,
            'files_count' => count($files),
        ]);
        
        return $finalUrl;
    }

    public function getServiceName(): string
    {
        return 'googledrive';
    }
}
