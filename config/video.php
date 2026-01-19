<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FFmpeg Configuration
    |--------------------------------------------------------------------------
    |
    | Paths to FFmpeg and FFprobe binaries.
    | Leave empty to use system PATH, or specify full paths.
    |
    */

    'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),

    /*
    |--------------------------------------------------------------------------
    | Enable Video Processing
    |--------------------------------------------------------------------------
    |
    | Set to false to disable video thumbnail generation entirely.
    |
    */

    'enabled' => env('VIDEO_PROCESSING_ENABLED', true),
];
