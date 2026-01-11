<?php

namespace App\Services\CloudStorage;

class CloudStorageFactory
{
    public static function make(string $service): CloudStorageServiceInterface
    {
        return match ($service) {
            'googledrive' => new GoogleDriveService,
            'google' => new GooglePhotosService,
            'dropbox' => new DropboxService,
            'onedrive' => new OneDriveService,
            'box' => new BoxService,
            'adobe' => new AdobeService,
            default => throw new \InvalidArgumentException("Unknown cloud storage service: {$service}"),
        };
    }
}
