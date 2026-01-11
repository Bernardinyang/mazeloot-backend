<?php

namespace App\Services\Storage;

use App\Models\UserFile;
use Illuminate\Support\Facades\Storage;

class UserStorageService
{
    /**
     * Get total storage used by a user (including all file variants)
     * Uses cached storage value for fast calculation
     *
     * @param  bool  $checkActualStorage  If true, verify against actual cloud storage (slower, for verification)
     * @return int Total size in bytes
     */
    public function getTotalStorageUsed(string $userUuid, bool $checkActualStorage = false): int
    {
        try {
            // Step 1: Check cached storage (should always exist)
            $cachedStorage = \App\Domains\Memora\Models\MemoraUserFileStorage::find($userUuid);

            if ($cachedStorage && ($cachedStorage->total_storage_bytes ?? 0) > 0) {
                // Use cached value - fastest path
                $totalSize = $cachedStorage->total_storage_bytes;

                // Only check actual storage if explicitly requested
                if ($checkActualStorage) {
                    $actualSize = $this->calculateActualStorageSize($userUuid);
                    if ($actualSize > 0) {
                        // Update cache with actual size
                        $this->updateStorageCache($userUuid, $actualSize);

                        return $actualSize;
                    }
                }

                return $totalSize;
            }

            // Step 2: If cache doesn't exist, calculate from user_files metadata (fast, no cloud calls)
            $totalSize = $this->calculateAndCacheStorage($userUuid);

            // Step 3: Only check online storage if explicitly requested and cache was missing
            if ($checkActualStorage && $totalSize == 0) {
                $actualSize = $this->calculateActualStorageSize($userUuid);
                if ($actualSize > 0) {
                    $this->updateStorageCache($userUuid, $actualSize);

                    return $actualSize;
                }
            }

            return $totalSize;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to get total storage used', [
                'user_uuid' => $userUuid,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Calculate storage from database metadata (no cloud storage calls)
     * Optimized to avoid N+1 queries using raw SQL
     */
    public function calculateAndCacheStorage(string $userUuid): int
    {
        // Use raw SQL to extract and sum storage from JSON metadata
        // This avoids loading all files into memory and N+1 queries
        // Exclude soft-deleted files from storage calculation
        $totalSize = \Illuminate\Support\Facades\DB::table('user_files')
            ->where('user_uuid', $userUuid)
            ->whereNull('deleted_at')
            ->get()
            ->sum(function ($file) {
                return $this->calculateFileSizeFromMetadata($file);
            });

        $this->updateStorageCache($userUuid, $totalSize);

        return $totalSize;
    }

    /**
     * Update storage cache for user
     */
    public function updateStorageCache(string $userUuid, int $totalBytes): void
    {
        \App\Domains\Memora\Models\MemoraUserFileStorage::updateOrCreate(
            ['user_uuid' => $userUuid],
            [
                'total_storage_bytes' => $totalBytes,
                'last_calculated_at' => now(),
            ]
        );
    }

    /**
     * Increment storage for user when file is uploaded
     */
    public function incrementStorage(string $userUuid, int $bytes): void
    {
        $storage = \App\Domains\Memora\Models\MemoraUserFileStorage::firstOrNew(['user_uuid' => $userUuid]);
        $storage->total_storage_bytes = ($storage->total_storage_bytes ?? 0) + $bytes;
        $storage->last_calculated_at = now();
        $storage->save();
    }

    /**
     * Decrement storage for user when file is deleted
     */
    public function decrementStorage(string $userUuid, int $bytes): void
    {
        $storage = \App\Domains\Memora\Models\MemoraUserFileStorage::find($userUuid);
        if ($storage) {
            $storage->total_storage_bytes = max(0, ($storage->total_storage_bytes ?? 0) - $bytes);
            $storage->save();
        }
    }

    /**
     * Calculate actual storage size by checking file system/cloud storage
     * Only called when checkActualStorage is true
     */
    protected function calculateActualStorageSize(string $userUuid): int
    {
        // Get files using raw query to avoid loading full models
        // Exclude soft-deleted files from storage calculation
        $files = \Illuminate\Support\Facades\DB::table('user_files')
            ->where('user_uuid', $userUuid)
            ->whereNull('deleted_at')
            ->select('uuid', 'path', 'size', 'metadata')
            ->get();

        $totalSize = 0;
        $filesProcessed = 0;
        $variantsFound = 0;
        $variantsNotFound = 0;

        foreach ($files as $file) {
            try {
                $metadata = json_decode($file->metadata ?? '{}', true) ?? [];
                $fileStats = [];
                $fileSize = $this->getFileSizeWithVariantsFromData($file, $metadata, $fileStats);
                $totalSize += $fileSize;
                $filesProcessed++;

                $variantsFound += $fileStats['found'] ?? 0;
                $variantsNotFound += $fileStats['not_found'] ?? 0;
            } catch (\Exception $e) {
                // Log error but continue with others
                \Illuminate\Support\Facades\Log::warning('Failed to calculate size for file', [
                    'file_uuid' => $file->uuid,
                    'provider' => $metadata['provider'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                // Fallback to database size if available
                if ($file->size) {
                    $totalSize += $file->size;
                }
            }
        }

        \Illuminate\Support\Facades\Log::info('Actual storage calculation completed', [
            'user_uuid' => $userUuid,
            'files_processed' => $filesProcessed,
            'variants_found' => $variantsFound,
            'variants_not_found' => $variantsNotFound,
            'total_size' => $totalSize,
        ]);

        return $totalSize;
    }

    /**
     * Get file size with variants from raw data (for actual storage check)
     *
     * @param  object  $file  Raw DB result
     * @param  array  $metadata  Parsed metadata
     * @param  array|null  $stats  Reference to array to store statistics
     * @return int Total size in bytes
     */
    protected function getFileSizeWithVariantsFromData($file, array $metadata, ?array &$stats = null): int
    {
        $stats = ['found' => 0, 'not_found' => 0];
        $totalSize = 0;
        $variants = $metadata['variants'] ?? [];

        // If no variants stored, use the file size from database
        if (empty($variants)) {
            return $file->size ?? 0;
        }

        // Get disk from provider
        $provider = $metadata['provider'] ?? config('upload.default_provider', 'local');
        $disk = $this->getDiskForProvider($provider);

        // Extract UUID from base path
        $uuid = null;
        if (preg_match('#uploads/images/([^/]+)#', $file->path ?? '', $matches)) {
            $uuid = $matches[1];
        }

        // Calculate size for each variant
        foreach ($variants as $variantName => $variantUrl) {
            if (empty($variantUrl)) {
                continue;
            }

            // Try to get path from URL or construct from base path
            $variantPath = $this->extractPathFromUrl($variantUrl, $file->path ?? '', $variantName, $uuid);

            if ($variantPath) {
                try {
                    // Check if file exists first (works for both local and cloud storage)
                    if (Storage::disk($disk)->exists($variantPath)) {
                        $size = Storage::disk($disk)->size($variantPath);
                        if ($size !== false && $size > 0) {
                            $totalSize += $size;
                            $stats['found']++;
                        }
                    } else {
                        $stats['not_found']++;
                    }
                } catch (\Exception $e) {
                    $stats['not_found']++;
                    \Illuminate\Support\Facades\Log::debug('Variant not found in storage', [
                        'variant' => $variantName,
                        'path' => $variantPath,
                        'disk' => $disk,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // If no variants found, fallback to database size
        if ($totalSize === 0 && $file->size) {
            $totalSize = $file->size;
        }

        return $totalSize;
    }

    /**
     * Get storage size for a single file including all variants
     *
     * @param  array|null  $stats  Reference to array to store statistics
     * @return int Total size in bytes
     */
    protected function getFileSizeWithVariants(UserFile $file, bool $useDatabaseFallback = true, ?array &$stats = null): int
    {
        $stats = ['found' => 0, 'not_found' => 0];
        $totalSize = 0;
        $variants = $file->metadata['variants'] ?? [];

        // If no variants stored, use the file size from database (fallback for older files)
        if (empty($variants)) {
            return $file->size ?? 0;
        }

        // Get disk from provider
        $provider = $file->metadata['provider'] ?? config('upload.default_provider', 'local');
        $disk = $this->getDiskForProvider($provider);

        // Extract UUID from base path (e.g., 'uploads/images/{uuid}' -> '{uuid}')
        $uuid = null;
        if (preg_match('#uploads/images/([^/]+)#', $file->path, $matches)) {
            $uuid = $matches[1];
        }

        // Calculate size for each variant
        foreach ($variants as $variantName => $variantUrl) {
            if (empty($variantUrl)) {
                continue;
            }

            // Try to get path from URL or construct from base path
            $variantPath = $this->extractPathFromUrl($variantUrl, $file->path, $variantName, $uuid);

            if ($variantPath) {
                try {
                    // Check if file exists first (works for both local and cloud storage)
                    if (Storage::disk($disk)->exists($variantPath)) {
                        $size = Storage::disk($disk)->size($variantPath);
                        if ($size !== false && $size > 0) {
                            $totalSize += $size;
                            $stats['found']++;

                            \Illuminate\Support\Facades\Log::debug('Found variant in storage', [
                                'variant' => $variantName,
                                'path' => $variantPath,
                                'disk' => $disk,
                                'size' => $size,
                                'provider' => $provider,
                            ]);
                        } else {
                            $stats['not_found']++;
                        }
                    } else {
                        $stats['not_found']++;
                        \Illuminate\Support\Facades\Log::debug('Variant not found in storage', [
                            'variant' => $variantName,
                            'path' => $variantPath,
                            'disk' => $disk,
                            'provider' => $provider,
                        ]);
                    }
                } catch (\Exception $e) {
                    $stats['not_found']++;
                    // Log error but continue with other variants
                    \Illuminate\Support\Facades\Log::warning("Failed to get size for variant {$variantName}", [
                        'path' => $variantPath,
                        'disk' => $disk,
                        'provider' => $provider,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $stats['not_found']++;
                \Illuminate\Support\Facades\Log::debug('Could not extract path from variant URL', [
                    'variant' => $variantName,
                    'url' => $variantUrl,
                    'base_path' => $file->path,
                ]);
            }
        }

        // If we couldn't get any variant sizes from storage, use database size estimate
        if ($totalSize === 0 && $file->size && $useDatabaseFallback) {
            // Estimate: multiply by average number of variants
            $estimatedVariantMultiplier = 1.5;

            return (int) ($file->size * $estimatedVariantMultiplier);
        }

        // If still 0 and no fallback, return database size as last resort
        if ($totalSize === 0 && $file->size) {
            return $file->size;
        }

        return $totalSize;
    }

    /**
     * Extract storage path from URL
     */
    protected function extractPathFromUrl(string $url, string $basePath, string $variantName, ?string $uuid = null): ?string
    {
        // Extract UUID if not provided
        if (! $uuid && preg_match('#uploads/images/([^/]+)#', $basePath, $matches)) {
            $uuid = $matches[1];
        }

        // For cloud storage (S3/R2), URLs might contain query params or be full URLs
        // Try to extract just the path portion
        $parsedUrl = parse_url($url);

        if (isset($parsedUrl['path'])) {
            $path = ltrim($parsedUrl['path'], '/');

            // Handle local storage paths (contains /storage/)
            if (str_contains($path, '/storage/')) {
                $path = ltrim(str_replace('/storage/', '', $path), '/');
            }

            // Handle paths that start with storage/
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, 8); // Remove 'storage/'
            }

            // If path starts with uploads/, return as is
            if (str_starts_with($path, 'uploads/')) {
                return $path;
            }

            // For cloud storage, path might be just the filename or relative path
            // Check if it matches the expected pattern
            if (preg_match('#uploads/images/[^/]+/.+#', $path)) {
                return $path;
            }
        }

        // Fallback: construct path from basePath, variant name, and extension from URL
        if ($uuid) {
            // Try to extract extension from URL (before query params)
            $extension = 'jpg'; // default
            $urlPath = $parsedUrl['path'] ?? $url;
            if (preg_match('#\.(jpg|jpeg|png|webp|gif|svg)(\?|$)#i', $urlPath, $matches)) {
                $extension = strtolower($matches[1]);
            }

            return "uploads/images/{$uuid}/{$variantName}.{$extension}";
        }

        return null;
    }

    /**
     * Get storage disk for provider
     */
    protected function getDiskForProvider(string $provider): string
    {
        return match ($provider) {
            'local' => config('upload.providers.local.disk', 'public'),
            's3' => config('upload.providers.s3.disk', 's3'),
            'r2' => config('upload.providers.r2.disk', 'r2'),
            default => 'public',
        };
    }

    /**
     * Get total storage used by media in a selection/proofing/collection
     * Optimized using raw SQL to avoid N+1 queries
     * Uses the same calculation method as getTotalStorageUsed for consistency
     *
     * @param  string  $phaseId  Selection/Proofing/Collection UUID
     * @param  string  $phaseType  'selection', 'proofing', or 'collection'
     * @return int Total size in bytes
     */
    public function getPhaseStorageUsed(string $phaseId, string $phaseType): int
    {
        try {
            // Build query to get user_file_uuids directly from media via media_sets
            // Single query, no N+1
            $column = match ($phaseType) {
                'selection' => 'selection_uuid',
                'proofing' => 'proof_uuid',
                'collection' => 'collection_uuid',
                default => null,
            };

            if (! $column) {
                return 0;
            }

            // Single optimized query: get all user file UUIDs for this phase
            // Exclude soft-deleted media from the calculation
            $userFileUuids = \Illuminate\Support\Facades\DB::table('memora_media')
                ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                ->where('memora_media_sets.'.$column, $phaseId)
                ->whereNotNull('memora_media.user_file_uuid')
                ->whereNull('memora_media.deleted_at')
                ->distinct()
                ->pluck('memora_media.user_file_uuid')
                ->filter()
                ->values();

            if ($userFileUuids->isEmpty()) {
                return 0;
            }

            // Use the same calculation method as calculateAndCacheStorage for consistency
            // Exclude soft-deleted files from storage calculation
            $totalSize = \Illuminate\Support\Facades\DB::table('user_files')
                ->whereIn('uuid', $userFileUuids)
                ->whereNull('deleted_at')
                ->get()
                ->sum(function ($file) {
                    return $this->calculateFileSizeFromMetadata($file);
                });

            return $totalSize;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to get phase storage used', [
                'phase_id' => $phaseId,
                'phase_type' => $phaseType,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Calculate file size from metadata (shared method for consistency)
     *
     * @param  object  $file  Database file record
     * @return int Size in bytes
     */
    public function calculateFileSizeFromMetadata($file): int
    {
        // Handle both cases: metadata may be array (from model) or JSON string (from raw query)
        $metadata = $file->metadata ?? null;
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?? [];
        } elseif (! is_array($metadata)) {
            $metadata = [];
        }

        // Use stored total_size_with_variants if available (new uploads)
        $storedTotal = $metadata['total_size_with_variants'] ?? null;
        if ($storedTotal) {
            return (int) $storedTotal;
        }

        // Fallback: calculate from variant_sizes if available
        $variantSizes = $metadata['variant_sizes'] ?? [];
        if (! empty($variantSizes) && is_array($variantSizes)) {
            return (int) array_sum($variantSizes);
        }

        // Last resort: use database size with estimate (old files)
        return (int) (($file->size ?? 0) * 1.5);
    }
}
