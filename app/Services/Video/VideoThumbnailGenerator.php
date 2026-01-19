<?php

namespace App\Services\Video;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoThumbnailGenerator
{
    /**
     * Generate thumbnail from video file
     *
     * @param  string  $outputPath  Optional custom output path
     * @return string|null Path to generated thumbnail or null if failed
     */
    public function generateThumbnail(UploadedFile $file, ?string $outputPath = null): ?string
    {
        $tempPath = $file->getRealPath();

        if (! $tempPath || ! file_exists($tempPath)) {
            Log::error("Video file not found at path: {$tempPath}");

            return null;
        }

        // Check if FFmpeg is available
        if (! $this->isFFmpegAvailable()) {
            Log::warning('FFmpeg is not available. Video thumbnail generation skipped.');

            return null;
        }

        // Generate temporary thumbnail path
        $thumbnailPath = $outputPath ?? sys_get_temp_dir().'/'.Str::uuid().'.jpg';

        try {
            $ffmpegPath = config('video.ffmpeg_path', 'ffmpeg');
            
            // Extract frame at 1 second (or first frame if video is shorter)
            // FFmpeg command: extract frame at 1s, resize to max 400px width, save as JPEG
            // Using scale=400:-2 to ensure height is divisible by 2 (required for codecs)
            // Using -pix_fmt yuvj420p for JPEG output and -threads 1 for shared hosting
            $command = sprintf(
                '%s -i %s -ss 00:00:01 -frames:v 1 -vf scale=400:-2 -pix_fmt yuvj420p -q:v 2 -threads 1 %s 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($tempPath),
                escapeshellarg($thumbnailPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || ! file_exists($thumbnailPath)) {
                // Try extracting first frame if 1 second fails
                $command = sprintf(
                    '%s -i %s -frames:v 1 -vf scale=400:-2 -pix_fmt yuvj420p -q:v 2 -threads 1 %s 2>&1',
                    escapeshellarg($ffmpegPath),
                    escapeshellarg($tempPath),
                    escapeshellarg($thumbnailPath)
                );

                exec($command, $output, $returnCode);

                if ($returnCode !== 0 || ! file_exists($thumbnailPath)) {
                    Log::error('Failed to generate video thumbnail. FFmpeg output: '.implode("\n", $output));

                    return null;
                }
            }

            return $thumbnailPath;
        } catch (\Exception $e) {
            Log::error('Exception while generating video thumbnail: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Upload thumbnail to storage and return URL
     *
     * @param  string  $thumbnailPath  Local path to thumbnail
     * @param  string  $videoUuid  UUID of the video file
     * @param  string  $disk  Storage disk name
     * @return string|null Public URL of uploaded thumbnail
     */
    public function uploadThumbnail(string $thumbnailPath, string $videoUuid, string $disk): ?string
    {
        if (! file_exists($thumbnailPath)) {
            return null;
        }

        try {
            $storagePath = 'uploads/videos/'.$videoUuid.'/thumbnail.jpg';
            $contents = file_get_contents($thumbnailPath);

            $uploaded = Storage::disk($disk)->put($storagePath, $contents, 'public');

            if (! $uploaded) {
                Log::error('Failed to upload video thumbnail to storage');

                return null;
            }

            // Get public URL
            $url = Storage::disk($disk)->url($storagePath);

            // Ensure absolute URL for local storage
            if ($disk === 'public' && ! filter_var($url, FILTER_VALIDATE_URL)) {
                $baseUrl = rtrim(config('app.url', 'http://localhost'), '/');
                $url = $baseUrl.'/'.ltrim($url, '/');
            }

            return $url;
        } catch (\Exception $e) {
            Log::error('Exception while uploading video thumbnail: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Check if FFmpeg is available on the system
     */
    protected function isFFmpegAvailable(): bool
    {
        // Check if video processing is disabled
        if (! config('video.enabled', true)) {
            return false;
        }

        // Check if exec() is disabled
        if (! function_exists('exec')) {
            Log::warning('exec() function is disabled. Video thumbnail generation unavailable.');
            return false;
        }

        $ffmpegPath = config('video.ffmpeg_path', 'ffmpeg');
        exec("{$ffmpegPath} -version 2>&1", $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Get video dimensions using FFprobe
     *
     * @return array{width: int, height: int}
     */
    public function getVideoDimensions(string $videoPath): array
    {
        if (! $this->isFFmpegAvailable()) {
            return ['width' => 0, 'height' => 0];
        }

        try {
            $ffprobePath = config('video.ffprobe_path', 'ffprobe');
            $command = sprintf(
                '%s -v error -select_streams v:0 -show_entries stream=width,height -of json %s',
                escapeshellarg($ffprobePath),
                escapeshellarg($videoPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $json = json_decode(implode("\n", $output), true);
                if (isset($json['streams'][0])) {
                    return [
                        'width' => (int) ($json['streams'][0]['width'] ?? 0),
                        'height' => (int) ($json['streams'][0]['height'] ?? 0),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get video dimensions: '.$e->getMessage());
        }

        return ['width' => 0, 'height' => 0];
    }

    /**
     * Cleanup temporary thumbnail file
     */
    public function cleanup(string $path): void
    {
        if (file_exists($path) && str_starts_with($path, sys_get_temp_dir())) {
            @unlink($path);
        }
    }
}
