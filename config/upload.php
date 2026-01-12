<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Upload Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default upload provider that will be used.
    | Supported: "local", "s3", "r2", "cloudinary"
    |
    */

    'default_provider' => env('UPLOAD_PROVIDER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each upload provider.
    |
    */

    'providers' => [
        'local' => [
            'disk' => env('FILESYSTEM_DISK', 'local'),
        ],

        's3' => [
            'disk' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],

        'r2' => [
            'disk' => 'r2',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'url' => env('R2_URL'),
        ],

        'cloudinary' => [
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'api_key' => env('CLOUDINARY_API_KEY'),
            'api_secret' => env('CLOUDINARY_API_SECRET'),
            'secure' => env('CLOUDINARY_SECURE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    |
    | Maximum file size and other upload constraints
    |
    */

    'max_size' => env('UPLOAD_MAX_SIZE', 262144000), // 250MB in bytes (default, can be overridden via env)

    /*
    |--------------------------------------------------------------------------
    | Phase-Specific Allowed File Types
    |--------------------------------------------------------------------------
    |
    | Define allowed file types per phase. If a phase is not specified,
    | it falls back to the default 'allowed_types' list.
    |
    */

    'allowed_types_by_phase' => [
        'selection' => [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'video/mp4',
            'video/webm',
        ],
        'proofing' => [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'video/mp4',
            'video/webm',
        ],
        'collection' => [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'video/mp4',
            'video/webm',
        ],
        'raw_files' => [
            // Standard Images
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/tiff',
            'image/tif',
            'image/bmp',
            'image/x-bmp',
            'image/heic',
            'image/heif',
            'image/x-heic',
            'image/x-heif',
            // Camera Raw Formats
            'image/x-canon-cr2',
            'image/x-canon-crw',
            'image/x-nikon-nef',
            'image/x-nikon-nrw',
            'image/x-sony-arw',
            'image/x-sony-sr2',
            'image/x-sony-srf',
            'image/x-pentax-pef',
            'image/x-olympus-orf',
            'image/x-fuji-raf',
            'image/x-panasonic-rw2',
            'image/x-panasonic-rwl',
            'image/x-adobe-dng',
            'image/x-3fr',
            'image/x-ari',
            'image/x-cap',
            'image/x-cin',
            'image/x-crw',
            'image/x-dcr',
            'image/x-dcs',
            'image/x-drf',
            'image/x-eip',
            'image/x-erf',
            'image/x-fff',
            'image/x-iiq',
            'image/x-k25',
            'image/x-kdc',
            'image/x-mef',
            'image/x-mos',
            'image/x-mrw',
            'image/x-nef',
            'image/x-nrw',
            'image/x-orf',
            'image/x-pef',
            'image/x-ptx',
            'image/x-pxn',
            'image/x-r3d',
            'image/x-raf',
            'image/x-raw',
            'image/x-rw2',
            'image/x-rwl',
            'image/x-rwz',
            'image/x-sr2',
            'image/x-srf',
            'image/x-srw',
            'image/x-x3f',
            // Videos
            'video/mp4',
            'video/x-m4v',
            'video/quicktime',
            'video/x-quicktime',
            'video/avi',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/x-matroska',
            'video/x-matroska-3d',
            'video/mp2t',
            'video/mpeg',
            'video/mpeg-system',
            'video/x-mpeg',
            'video/x-mpeg-system',
            'video/x-ms-asf',
            'video/x-flv',
            'video/3gpp',
            'video/3gpp2',
            'video/x-3gpp',
            'video/x-3gpp2',
            'video/x-dv',
            'video/dv',
            'video/x-h264',
            'video/h264',
            'video/x-h265',
            'video/h265',
            'video/hevc',
            'video/x-ogm',
            'video/ogg',
            'video/webm',
            'video/x-theora',
            'video/x-vp8',
            'video/x-vp9',
            'video/x-mxf',
            'video/mxf',
            'video/x-avchd',
            'video/avchd',
            'video/x-mts',
            'video/x-m2ts',
            'video/x-m2t',
            'video/x-mod',
            'video/x-tod',
            'video/x-vob',
            'video/x-ts',
            'video/x-trp',
            'video/x-rm',
            'video/x-rmvb',
            'video/x-vivo',
            // Audio
            'audio/mpeg',
            'audio/mp3',
            'audio/x-mpeg',
            'audio/x-mpeg-3',
            'audio/mp4',
            'audio/x-m4a',
            'audio/m4a',
            'audio/wav',
            'audio/x-wav',
            'audio/wave',
            'audio/vnd.wave',
            'audio/aac',
            'audio/x-aac',
            'audio/flac',
            'audio/x-flac',
            'audio/ogg',
            'audio/vorbis',
            'audio/x-vorbis',
            'audio/opus',
            'audio/x-opus',
            'audio/amr',
            'audio/x-amr',
            'audio/3gpp',
            'audio/x-3gpp',
            'audio/aiff',
            'audio/x-aiff',
            'audio/aif',
            'audio/x-aif',
            'audio/x-pn-aiff',
            'audio/basic',
            'audio/x-au',
            'audio/x-pn-au',
            'audio/x-pn-wav',
            'audio/x-pn-windows-acm',
            'audio/vnd.dlna.adts',
            'audio/x-ms-wma',
            'audio/x-ms-wax',
            'audio/x-realaudio',
            'audio/x-pn-realaudio',
            'audio/x-pn-realaudio-plugin',
            'audio/x-wavpack',
            'audio/x-ape',
            'audio/x-shorten',
            'audio/x-tak',
            'audio/x-tta',
            // Archives
            'application/zip',
            'application/x-zip-compressed',
            'application/x-rar-compressed',
            'application/x-rar',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip',
            'application/x-gzip',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Allowed File Types
    |--------------------------------------------------------------------------
    |
    | Default allowed types used when no phase is specified or phase is not
    | in the allowed_types_by_phase list.
    |
    */

    'allowed_types' => [
        // Standard Images
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/tiff',
        'image/tif',
        'image/bmp',
        'image/x-bmp',
        'image/heic',
        'image/heif',
        'image/x-heic',
        'image/x-heif',
        // Camera Raw Formats
        'image/x-canon-cr2',
        'image/x-canon-crw',
        'image/x-nikon-nef',
        'image/x-nikon-nrw',
        'image/x-sony-arw',
        'image/x-sony-sr2',
        'image/x-sony-srf',
        'image/x-pentax-pef',
        'image/x-olympus-orf',
        'image/x-fuji-raf',
        'image/x-panasonic-rw2',
        'image/x-panasonic-rwl',
        'image/x-adobe-dng',
        'image/x-3fr',
        'image/x-ari',
        'image/x-cap',
        'image/x-cin',
        'image/x-crw',
        'image/x-dcr',
        'image/x-dcs',
        'image/x-drf',
        'image/x-eip',
        'image/x-erf',
        'image/x-fff',
        'image/x-iiq',
        'image/x-k25',
        'image/x-kdc',
        'image/x-mef',
        'image/x-mos',
        'image/x-mrw',
        'image/x-nef',
        'image/x-nrw',
        'image/x-orf',
        'image/x-pef',
        'image/x-ptx',
        'image/x-pxn',
        'image/x-r3d',
        'image/x-raf',
        'image/x-raw',
        'image/x-rw2',
        'image/x-rwl',
        'image/x-rwz',
        'image/x-sr2',
        'image/x-srf',
        'image/x-srw',
        'image/x-x3f',
        // Videos
        'video/mp4',
        'video/x-m4v',
        'video/quicktime',
        'video/x-quicktime',
        'video/avi',
        'video/x-msvideo',
        'video/x-ms-wmv',
        'video/x-matroska',
        'video/x-matroska-3d',
        'video/mp2t',
        'video/mpeg',
        'video/mpeg-system',
        'video/x-mpeg',
        'video/x-mpeg-system',
        'video/x-ms-asf',
        'video/x-flv',
        'video/3gpp',
        'video/3gpp2',
        'video/x-3gpp',
        'video/x-3gpp2',
        'video/x-dv',
        'video/dv',
        'video/x-h264',
        'video/h264',
        'video/x-h265',
        'video/h265',
        'video/hevc',
        'video/x-ogm',
        'video/ogg',
        'video/webm',
        'video/x-theora',
        'video/x-vp8',
        'video/x-vp9',
        'video/x-mxf',
        'video/mxf',
        'video/x-avchd',
        'video/avchd',
        'video/x-mts',
        'video/x-m2ts',
        'video/x-m2t',
        'video/x-mod',
        'video/x-tod',
        'video/x-vob',
        'video/x-ts',
        'video/x-trp',
        'video/x-rm',
        'video/x-rmvb',
        'video/x-vivo',
        // Audio
        'audio/mpeg',
        'audio/mp3',
        'audio/x-mpeg',
        'audio/x-mpeg-3',
        'audio/mp4',
        'audio/x-m4a',
        'audio/m4a',
        'audio/wav',
        'audio/x-wav',
        'audio/wave',
        'audio/vnd.wave',
        'audio/aac',
        'audio/x-aac',
        'audio/flac',
        'audio/x-flac',
        'audio/ogg',
        'audio/vorbis',
        'audio/x-vorbis',
        'audio/opus',
        'audio/x-opus',
        'audio/amr',
        'audio/x-amr',
        'audio/3gpp',
        'audio/x-3gpp',
        'audio/aiff',
        'audio/x-aiff',
        'audio/aif',
        'audio/x-aif',
        'audio/x-pn-aiff',
        'audio/basic',
        'audio/x-au',
        'audio/x-pn-au',
        'audio/x-pn-wav',
        'audio/x-pn-windows-acm',
        'audio/vnd.dlna.adts',
        'audio/x-ms-wma',
        'audio/x-ms-wax',
        'audio/x-realaudio',
        'audio/x-pn-realaudio',
        'audio/x-pn-realaudio-plugin',
        'audio/x-wavpack',
        'audio/x-ape',
        'audio/x-shorten',
        'audio/x-tak',
        'audio/x-tta',
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Archives
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar-compressed',
        'application/x-rar',
        'application/x-7z-compressed',
        'application/x-tar',
        'application/gzip',
        'application/x-gzip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quota Configuration
    |--------------------------------------------------------------------------
    |
    | Upload quota settings per domain/user
    |
    */

    'quota' => [
        'per_domain' => [
            // 'memora' => 1073741824, // 1GB in bytes
        ],
        'per_user' => [
            // Default user quota in bytes
            'default' => 524288000, // 500MB
        ],
    ],
];
