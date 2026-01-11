<?php

namespace App\Services\CloudStorage;

use App\Services\CloudStorage\GoogleDriveService;
use App\Services\CloudStorage\GooglePhotosService;
use App\Services\CloudStorage\DropboxService;
use App\Services\CloudStorage\OneDriveService;
use App\Services\CloudStorage\BoxService;
use App\Services\CloudStorage\AdobeService;

class CloudStorageFactory
{
    public static function make(string $service): CloudStorageServiceInterface
    {
        return match ($service) {
            'googledrive' => new GoogleDriveService(),
            'google' => new GooglePhotosService(),
            'dropbox' => new DropboxService(),
            'onedrive' => new OneDriveService(),
            'box' => new BoxService(),
            'adobe' => new AdobeService(),
            default => throw new \InvalidArgumentException("Unknown cloud storage service: {$service}"),
        };
    }
}
