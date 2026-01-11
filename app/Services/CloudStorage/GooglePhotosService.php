<?php

namespace App\Services\CloudStorage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePhotosService implements CloudStorageServiceInterface
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
            'https://www.googleapis.com/auth/photoslibrary.appendonly',
            'https://www.googleapis.com/auth/photoslibrary.readonly', // Needed to list albums
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

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params);
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

        if (! $response->successful()) {
            Log::error('Google Photos token exchange failed', [
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

        if (! $response->successful()) {
            throw new \Exception('Failed to refresh token');
        }

        return $response->json();
    }

    public function uploadFile(string $filePath, string $fileName, string $accessToken, ?string $folderName = null): string
    {
        // Step 1: Upload binary data
        $fileContents = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);

        $uploadResponse = Http::withToken($accessToken)
            ->withBody($fileContents, $mimeType)
            ->post('https://photoslibrary.googleapis.com/v1/uploads');

        if (! $uploadResponse->successful()) {
            Log::error('Google Photos upload failed', [
                'response' => $uploadResponse->body(),
            ]);
            throw new \Exception('Failed to upload file to Google Photos');
        }

        $uploadToken = $uploadResponse->body();

        // Step 2: Create media item
        $createResponse = Http::withToken($accessToken)
            ->post('https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate', [
                'newMediaItems' => [
                    [
                        'description' => $fileName,
                        'simpleMediaItem' => [
                            'uploadToken' => $uploadToken,
                        ],
                    ],
                ],
            ]);

        if (! $createResponse->successful()) {
            Log::error('Google Photos batchCreate failed', [
                'status' => $createResponse->status(),
                'response' => $createResponse->body(),
            ]);
            throw new \Exception('Failed to create media item in Google Photos: '.$createResponse->body());
        }

        $responseData = $createResponse->json();

        // Check if newMediaItemResults exists and has items
        if (! isset($responseData['newMediaItemResults']) || empty($responseData['newMediaItemResults'])) {
            Log::error('Google Photos batchCreate returned no results', [
                'response' => $responseData,
            ]);
            throw new \Exception('Google Photos API returned no media item results');
        }

        $result = $responseData['newMediaItemResults'][0];

        // Check status - Google Photos returns status.code and status.message
        if (isset($result['status'])) {
            $statusCode = $result['status']['code'] ?? null;
            $statusMessage = $result['status']['message'] ?? 'Unknown error';

            if ($statusCode !== 'OK' && $statusCode !== null) {
                Log::error('Google Photos batchCreate returned error status', [
                    'status' => $result['status'],
                    'result' => $result,
                    'full_response' => $responseData,
                ]);
                throw new \Exception('Failed to create media item in Google Photos: '.$statusMessage);
            }
        }

        // Check if mediaItem exists
        if (! isset($result['mediaItem'])) {
            Log::error('Google Photos batchCreate missing mediaItem', [
                'result' => $result,
                'full_response' => $responseData,
            ]);

            // Try to extract more detailed error
            $errorDetails = [];
            if (isset($result['status'])) {
                $errorDetails[] = 'Status: '.json_encode($result['status']);
            }
            throw new \Exception('Google Photos API response missing mediaItem. '.implode(' ', $errorDetails));
        }

        $mediaItem = $result['mediaItem'];

        // Return productUrl or baseUrl, fallback to Google Photos home
        return $mediaItem['productUrl'] ?? $mediaItem['baseUrl'] ?? 'https://photos.google.com';
    }

    public function supportsZipUpload(): bool
    {
        return false; // Google Photos only accepts images/videos, not ZIP files
    }

    public function uploadFiles(array $files, string $accessToken, string $albumName = 'Collection'): string
    {
        // Step 1: Check if album already exists, if not create it
        $albumId = null;

        // List existing albums to find matching name
        $pageToken = null;
        $foundAlbum = null;

        do {
            $params = [];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $listResponse = Http::withToken($accessToken)
                ->get('https://photoslibrary.googleapis.com/v1/albums', $params);

            if ($listResponse->successful()) {
                $listData = $listResponse->json();
                $albums = $listData['albums'] ?? [];

                // Search for album with matching title
                foreach ($albums as $album) {
                    if (isset($album['title']) && $album['title'] === $albumName) {
                        $foundAlbum = $album;
                        break;
                    }
                }

                // Get next page token
                $pageToken = $listData['nextPageToken'] ?? null;

                // Stop if we found the album
                if ($foundAlbum) {
                    break;
                }
            } else {
                $responseBody = $listResponse->json();
                $errorCode = $responseBody['error']['code'] ?? null;
                $errorMessage = $responseBody['error']['message'] ?? '';

                // If it's a scope/permission issue, skip listing and create new album
                if ($errorCode === 403 && str_contains($errorMessage, 'insufficient authentication scopes')) {
                    Log::info('Cannot list Google Photos albums - insufficient scope, will create new album', [
                        'note' => 'User needs to re-authorize with photoslibrary.readonly scope',
                    ]);
                    break; // Exit loop, will create new album below
                }

                Log::warning('Failed to list Google Photos albums', [
                    'response' => $listResponse->body(),
                ]);
                break;
            }
        } while ($pageToken && ! $foundAlbum);

        // Use existing album or create new one
        if ($foundAlbum) {
            $albumId = $foundAlbum['id'];
            Log::info('Google Photos album found (reusing existing)', [
                'album_id' => $albumId,
                'album_name' => $albumName,
            ]);
        } else {
            // Create new album
            $albumResponse = Http::withToken($accessToken)
                ->post('https://photoslibrary.googleapis.com/v1/albums', [
                    'album' => [
                        'title' => $albumName,
                    ],
                ]);

            if ($albumResponse->successful()) {
                $albumData = $albumResponse->json();
                $albumId = $albumData['id'] ?? null;
                $productUrl = $albumData['productUrl'] ?? null;

                Log::info('Google Photos album created', [
                    'album_id' => $albumId,
                    'album_name' => $albumName,
                    'product_url' => $productUrl,
                ]);
            } else {
                Log::warning('Failed to create Google Photos album, will upload without album', [
                    'response' => $albumResponse->body(),
                ]);
            }
        }

        // Step 2: Upload files and organize by folder (set)
        $uploadTokens = [];
        $currentFolder = null;
        $folderItems = [];

        foreach ($files as $file) {
            $folder = $file['folder'] ?? 'Uncategorized';

            // Get MIME type from file data or detect from content
            $mimeType = $file['mime_type'] ?? '';
            if (! $mimeType && isset($file['content'])) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_buffer($finfo, $file['content']);
                finfo_close($finfo);
            }
            if (! $mimeType && isset($file['path']) && file_exists($file['path'])) {
                $mimeType = mime_content_type($file['path']);
            }

            // Only process image/video files (skip other types)
            if (! str_starts_with($mimeType, 'image/') && ! str_starts_with($mimeType, 'video/')) {
                continue;
            }

            try {
                // Upload binary data
                $fileContents = $file['content'] ?? file_get_contents($file['path']);
                if (! $fileContents) {
                    continue;
                }

                $uploadResponse = Http::withToken($accessToken)
                    ->withBody($fileContents, $mimeType)
                    ->post('https://photoslibrary.googleapis.com/v1/uploads');

                if ($uploadResponse->successful()) {
                    $uploadToken = $uploadResponse->body();
                    $uploadTokens[] = [
                        'uploadToken' => $uploadToken,
                        'folder' => $folder,
                        'description' => $file['name'] ?? '',
                    ];

                    // Track items per folder for album organization
                    if (! isset($folderItems[$folder])) {
                        $folderItems[$folder] = [];
                    }
                    $folderItems[$folder][] = $uploadToken;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to upload file to Google Photos', [
                    'file' => $file['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($uploadTokens)) {
            throw new \Exception('No files were successfully uploaded to Google Photos');
        }

        // Step 3: Create media items in batches (Google Photos API supports up to 50 per batch)
        $batchSize = 50;
        $batches = array_chunk($uploadTokens, $batchSize);
        $createdMediaItems = [];

        foreach ($batches as $batch) {
            $newMediaItems = array_map(function ($item) {
                return [
                    'description' => $item['description'],
                    'simpleMediaItem' => [
                        'uploadToken' => $item['uploadToken'],
                    ],
                ];
            }, $batch);

            $createResponse = Http::withToken($accessToken)
                ->post('https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate', [
                    'newMediaItems' => $newMediaItems,
                    'albumId' => $albumId, // Add all items to the album if created
                ]);

            if ($createResponse->successful()) {
                $responseData = $createResponse->json();
                if (isset($responseData['newMediaItemResults'])) {
                    foreach ($responseData['newMediaItemResults'] as $result) {
                        if (isset($result['mediaItem']) && ($result['status']['code'] ?? null) === 'OK') {
                            $createdMediaItems[] = $result['mediaItem'];
                        }
                    }
                }
            }
        }

        // Return album product URL (preferred) or first media item URL
        if ($albumId) {
            // Get album details to get productUrl
            try {
                $albumDetailsResponse = Http::withToken($accessToken)
                    ->get("https://photoslibrary.googleapis.com/v1/albums/{$albumId}");

                if ($albumDetailsResponse->successful()) {
                    $albumDetails = $albumDetailsResponse->json();
                    $productUrl = $albumDetails['productUrl'] ?? null;
                    if ($productUrl) {
                        Log::info('Google Photos upload completed', [
                            'album_id' => $albumId,
                            'album_url' => $productUrl,
                            'files_uploaded' => count($uploadTokens),
                            'media_items_created' => count($createdMediaItems),
                        ]);

                        return $productUrl;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get album details for URL', [
                    'album_id' => $albumId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Fallback to constructed URL
            $albumUrl = "https://photos.google.com/album/{$albumId}";
            Log::info('Google Photos upload completed (using fallback URL)', [
                'album_id' => $albumId,
                'album_url' => $albumUrl,
                'files_uploaded' => count($uploadTokens),
                'media_items_created' => count($createdMediaItems),
            ]);

            return $albumUrl;
        }

        // Fallback to first media item URL if album creation failed
        if (! empty($createdMediaItems)) {
            $fallbackUrl = $createdMediaItems[0]['productUrl'] ?? $createdMediaItems[0]['baseUrl'] ?? 'https://photos.google.com';
            Log::info('Google Photos upload completed (no album)', [
                'fallback_url' => $fallbackUrl,
                'files_uploaded' => count($uploadTokens),
                'media_items_created' => count($createdMediaItems),
            ]);

            return $fallbackUrl;
        }

        Log::warning('Google Photos upload completed but no URL generated', [
            'files_uploaded' => count($uploadTokens),
            'media_items_created' => count($createdMediaItems),
        ]);

        return 'https://photos.google.com';
    }

    public function getServiceName(): string
    {
        return 'google';
    }
}
