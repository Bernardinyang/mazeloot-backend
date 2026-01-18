<?php

/**
 * Media and camera recorder file types allowed for raw files
 * Includes all common image, video, audio, and archive formats
 */

return [
    // Image formats
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'image/bmp',
    'image/tiff',
    'image/tif',
    'image/x-icon',
    'image/x-ms-bmp',
    'image/heic',
    'image/heif',

    // Camera RAW formats - Canon
    'image/x-canon-cr2',
    'image/x-canon-cr3',
    'image/x-canon-crw',

    // Camera RAW formats - Nikon
    'image/x-nikon-nef',
    'image/x-nikon-nrw',

    // Camera RAW formats - Sony
    'image/x-sony-arw',
    'image/x-sony-sr2',
    'image/x-sony-srf',

    // Camera RAW formats - Other brands
    'image/x-fuji-raf',
    'image/x-olympus-orf',
    'image/x-panasonic-rw2',
    'image/x-pentax-pef',
    'image/x-samsung-srw',
    'image/x-sigma-x3f',
    'image/x-adobe-dng',

    // Camera RAW formats - Legacy/Older
    'image/x-kodak-kdc',
    'image/x-kodak-dcr',
    'image/x-raw',
    'image/x-panasonic-raw',

    // Video formats - Standard containers
    'video/mp4',
    'video/x-m4v',
    'video/quicktime',
    'video/x-msvideo',
    'video/avi',
    'video/x-matroska',
    'video/mkv',
    'video/webm',
    'video/3gpp',
    'video/3gpp2',
    'video/x-flv',
    'video/x-ms-wmv',
    'video/mpeg',
    'video/mpg',
    'video/x-ms-asf',
    'video/x-ms-wm',
    'video/x-ms-wmx',
    'video/x-ms-wvx',
    'video/ogg',
    'video/x-ogm',
    'video/x-ogm+ogg',
    'video/dv',
    'video/mj2',
    'video/x-mng',
    'video/x-nsv',
    'video/x-rm',
    'video/x-rmvb',

    // Video formats - Camcorders & Professional
    'video/mp2t',
    'video/mts',
    'video/m2ts',
    'video/vnd.dlna.mpeg-tts',
    'application/x-mpegURL',
    'video/x-mod',
    'video/x-tod',
    'application/mxf',
    'application/x-mxf',

    // RAW Video formats - Professional/Cinema
    'video/x-canon-crm',
    'image/x-cinema-dng',
    'application/x-redcode-r3d',
    'application/x-blackmagic-raw',
    'application/x-arriraw',

    // Audio formats (for video recordings with audio tracks)
    'audio/mpeg',
    'audio/mp3',
    'audio/mp4',
    'audio/x-m4a',
    'audio/x-m4p',
    'audio/aac',
    'audio/wav',
    'audio/x-wav',
    'audio/wave',
    'audio/webm',
    'audio/ogg',
    'audio/flac',
    'audio/x-flac',
    'audio/opus',
    'audio/aiff',
    'audio/x-aiff',
    'audio/x-aac',
    'audio/x-caf',
    'audio/x-ms-wma',
    'audio/wma',
    'audio/amr',
    'audio/x-amr',
    'audio/3gpp',
    'audio/3gpp2',
    'audio/x-ms-wax',
    'audio/vnd.wave',
    'audio/basic',
    'audio/midi',
    'audio/x-midi',

    // Archive formats
    'application/zip',
    'application/x-zip-compressed',
    'application/x-zip',
    'application/x-rar-compressed',
    'application/x-rar',
    'application/x-7z-compressed',
    'application/x-tar',
    'application/gzip',
    'application/x-gzip',
    'application/x-compress',
    'application/x-compressed',

    // Generic/unknown RAW formats (for devices with proprietary or uncommon formats)
    'application/octet-stream',
    'application/x-raw-image',
];
