<?php

namespace App\Services\CloudStorage;

interface CloudStorageServiceInterface
{
    /**
     * Get OAuth authorization URL
     */
    public function getAuthorizationUrl(string $state, string $redirectUri): string;

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array;

    /**
     * Refresh access token
     */
    public function refreshToken(string $refreshToken): array;

    /**
     * Upload file to cloud storage
     */
    public function uploadFile(string $filePath, string $fileName, string $accessToken, ?string $folderName = null): string;

    /**
     * Check if service supports ZIP file uploads
     */
    public function supportsZipUpload(): bool;

    /**
     * Upload multiple files organized by folders/sets
     *
     * @param  array  $files  Array of ['path' => string, 'name' => string, 'folder' => string, 'content' => string]
     * @param  string  $accessToken  OAuth access token
     * @param  string  $albumName  Name for album/folder (if applicable)
     * @return string URL to album/folder or first uploaded file
     */
    public function uploadFiles(array $files, string $accessToken, string $albumName = 'Collection'): string;

    /**
     * Get service name
     */
    public function getServiceName(): string;
}
