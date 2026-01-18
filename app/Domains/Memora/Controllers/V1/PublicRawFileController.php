<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraRawFile;
use App\Domains\Memora\Requests\V1\CompleteRawFileRequest;
use App\Domains\Memora\Resources\V1\RawFileResource;
use App\Domains\Memora\Services\GuestRawFileService;
use App\Domains\Memora\Services\RawFileService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public Raw File Controller
 *
 * Handles public/guest access to raw files.
 * These endpoints are protected by guest token middleware (not user authentication).
 * Users must generate a guest token before accessing these routes.
 */
class PublicRawFileController extends Controller
{
    protected RawFileService $rawFileService;

    protected GuestRawFileService $guestRawFileService;

    public function __construct(RawFileService $rawFileService, GuestRawFileService $guestRawFileService)
    {
        $this->rawFileService = $rawFileService;
        $this->guestRawFileService = $guestRawFileService;
    }

    /**
     * Check raw file status (public endpoint - no authentication required)
     * Returns status and ownership info for quick validation
     */
    public function checkStatus(Request $request, string $id): JsonResponse
    {
        try {
            $rawFile = MemoraRawFile::query()
                ->where('uuid', $id)
                ->select('uuid', 'status', 'user_uuid', 'name')
                ->firstOrFail();

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $rawFile->user_uuid === $userUuid;
            }

            return ApiResponse::success([
                'id' => $rawFile->uuid,
                'status' => $rawFile->status->value,
                'name' => $rawFile->name,
                'isOwner' => $isOwner,
                'isAccessible' => in_array($rawFile->status->value, ['active', 'completed']) || ($rawFile->status->value === 'draft' && $isOwner),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw file not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to check raw file status', [
                'raw_file_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to check raw file status', 'CHECK_FAILED', 500);
        }
    }

    /**
     * Get a raw file (protected by guest token)
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify guest token exists
        if (! $guestToken) {
            return ApiResponse::error('Guest token is required', 'GUEST_TOKEN_MISSING', 401);
        }

        // Verify the token belongs to this raw file
        if ($guestToken->raw_file_uuid !== $id) {
            return ApiResponse::error('Token does not match raw file', 'INVALID_TOKEN', 403);
        }

        try {
            // For guest access, find the raw file without user filtering
            $rawFile = MemoraRawFile::query()
                ->where('uuid', $id)
                ->with(['mediaSets' => function ($query) {
                    $query->withCount('media')->orderBy('order');
                }])
                ->firstOrFail();

            // Allow access if raw file status is 'active' or 'completed' (view-only for completed)
            if (! in_array($rawFile->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Raw file is not accessible', 'RAW_FILE_NOT_ACCESSIBLE', 403);
            }

            return ApiResponse::success(new RawFileResource($rawFile));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw file not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch raw file for guest', [
                'raw_file_id' => $id,
                'token_id' => $guestToken->uuid ?? null,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch raw file', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Verify password for a raw file (public endpoint - no authentication required)
     * Used before generating guest token to verify password protection
     */
    public function verifyPassword(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        try {
            $rawFile = MemoraRawFile::query()
                ->where('uuid', $id)
                ->select('uuid', 'password', 'status')
                ->firstOrFail();

            // Check if raw file has password protection
            if (empty($rawFile->password)) {
                return ApiResponse::error('Raw file does not have password protection', 'NO_PASSWORD', 400);
            }

            // Verify password (plain text comparison since passwords are stored in plain text)
            if ($rawFile->password !== $request->input('password')) {
                return ApiResponse::error('Incorrect password', 'INVALID_PASSWORD', 401);
            }

            // Check if raw file is accessible
            if (! in_array($rawFile->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Raw file is not accessible', 'RAW_FILE_NOT_ACCESSIBLE', 403);
            }

            return ApiResponse::success([
                'verified' => true,
                'message' => 'Password verified successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw file not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to verify password', [
                'raw_file_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to verify password', 'VERIFY_FAILED', 500);
        }
    }

    /**
     * Verify download PIN for a raw file (public endpoint - no authentication required)
     */
    public function verifyDownloadPin(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ]);

        try {
            $rawFile = MemoraRawFile::query()
                ->where('uuid', $id)
                ->firstOrFail();

            $status = $rawFile->status?->value ?? $rawFile->status;

            // Only allow access if raw file is active or completed
            if (! in_array($status, ['active', 'completed'])) {
                return ApiResponse::error('Raw file is not accessible', 'RAW_FILE_NOT_ACCESSIBLE', 403);
            }

            $settings = $rawFile->settings ?? [];
            $downloadPin = $settings['download']['downloadPin'] ?? $settings['downloadPin'] ?? null;
            $downloadPinEnabled = $settings['download']['downloadPinEnabled'] ?? ! empty($downloadPin);

            if (! $downloadPinEnabled || ! $downloadPin) {
                return ApiResponse::success(['verified' => true]);
            }

            $verified = $request->input('pin') === $downloadPin;

            return ApiResponse::success(['verified' => $verified]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw file not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to verify download PIN', [
                'raw_file_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to verify download PIN', 'VERIFY_FAILED', 500);
        }
    }

    /**
     * Get selected filenames (protected by guest token)
     */
    public function getSelectedFilenames(Request $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify guest token exists
        if (! $guestToken) {
            return ApiResponse::error('Guest token is required', 'GUEST_TOKEN_MISSING', 401);
        }

        // Verify the token belongs to this raw file
        if ($guestToken->raw_file_uuid !== $id) {
            return ApiResponse::error('Token does not match raw file', 'INVALID_TOKEN', 403);
        }

        $setId = $request->query('setId');
        $result = $this->rawFileService->getSelectedFilenames($id, $setId);

        return ApiResponse::success($result);
    }

    /**
     * Complete a raw file (protected by guest token)
     * Accepts media UUIDs to mark as selected
     */
    public function complete(CompleteRawFileRequest $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this raw file
        if ($guestToken->raw_file_uuid !== $id) {
            return ApiResponse::error('Token does not match raw file', 'INVALID_TOKEN', 403);
        }

        // Complete raw file with media UUIDs and guest email
        $rawFile = $this->rawFileService->complete(
            $id,
            $request->validated()['mediaIds'],
            $guestToken->email
        );

        // Mark token as used
        $this->guestRawFileService->markTokenAsUsed($guestToken->token);

        return ApiResponse::success($rawFile);
    }

    /**
     * Initiate ZIP download generation
     */
    public function initiateZipDownload(Request $request, string $id): JsonResponse
    {
        try {
            // Validate raw file access (download PIN)
            $rawFile = MemoraRawFile::where('uuid', $id)->firstOrFail();

            $status = $rawFile->status?->value ?? $rawFile->status;
            if (! in_array($status, ['active', 'completed'])) {
                return ApiResponse::error('Raw file is not accessible', 'RAW_FILE_NOT_ACCESSIBLE', 403);
            }

            $settings = $rawFile->settings ?? [];
            $downloadSettings = $settings['download'] ?? [];

            // Check download PIN
            $downloadPinEnabled = $downloadSettings['downloadPinEnabled'] ?? ! empty($downloadSettings['downloadPin'] ?? null);
            $downloadPin = $downloadSettings['downloadPin'] ?? $settings['downloadPin'] ?? null;

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $rawFile->user_uuid === $userUuid;
            }

            if ($downloadPinEnabled && $downloadPin && ! $isOwner) {
                $providedPin = $request->header('X-Download-PIN');
                if (! $providedPin || $providedPin !== $downloadPin) {
                    return ApiResponse::error('Download PIN required', 'DOWNLOAD_PIN_REQUIRED', 401);
                }
            }

            $validated = $request->validate([
                'setIds' => 'nullable|array',
                'setIds.*' => 'uuid',
            ]);

            // If no setIds provided, get all sets
            $setIds = $validated['setIds'] ?? [];
            if (empty($setIds)) {
                $sets = \App\Domains\Memora\Models\MemoraMediaSet::where('raw_file_uuid', $id)->pluck('uuid')->toArray();
                $setIds = $sets;
            }

            if (empty($setIds)) {
                return ApiResponse::error('No sets found to download', 'NO_SETS', 404);
            }

            $token = bin2hex(random_bytes(16));
            $userEmail = $request->header('X-Raw-File-Email');

            // Store ZIP task in cache
            $zipTask = [
                'token' => $token,
                'raw_file_id' => $id,
                'set_ids' => $setIds,
                'email' => $userEmail,
                'status' => 'processing',
                'created_at' => now(),
            ];

            \Illuminate\Support\Facades\Cache::put("raw_file_zip_download_{$token}", $zipTask, now()->addHours(24));

            // Dispatch job to generate ZIP
            \App\Domains\Memora\Jobs\GenerateRawFileZipDownloadJob::dispatch(
                $token,
                $id,
                $setIds,
                $userEmail
            );

            return ApiResponse::success([
                'token' => $token,
                'status' => 'processing',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to initiate raw file ZIP download', [
                'raw_file_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to initiate download', 'INITIATE_FAILED', 500);
        }
    }

    /**
     * Get ZIP download status
     */
    public function getZipDownloadStatus(Request $request, string $id, string $token): JsonResponse
    {
        try {
            // Check download PIN if required
            $rawFile = MemoraRawFile::where('uuid', $id)->firstOrFail();

            $settings = $rawFile->settings ?? [];
            $downloadSettings = $settings['download'] ?? [];

            $downloadPinEnabled = $downloadSettings['downloadPinEnabled'] ?? ! empty($downloadSettings['downloadPin'] ?? null);
            $downloadPin = $downloadSettings['downloadPin'] ?? $settings['downloadPin'] ?? null;

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $rawFile->user_uuid === $userUuid;
            }

            if ($downloadPinEnabled && $downloadPin && ! $isOwner) {
                $providedPin = $request->header('X-Download-PIN');
                if (! $providedPin || $providedPin !== $downloadPin) {
                    return ApiResponse::error('Download PIN required', 'DOWNLOAD_PIN_REQUIRED', 401);
                }
            }

            $zipTask = \Illuminate\Support\Facades\Cache::get("raw_file_zip_download_{$token}");

            if (! $zipTask) {
                return ApiResponse::error('Download not found', 'NOT_FOUND', 404);
            }

            if ($zipTask['raw_file_id'] !== $id) {
                return ApiResponse::error('Invalid token', 'INVALID_TOKEN', 403);
            }

            return ApiResponse::success([
                'status' => $zipTask['status'] ?? 'processing',
                'zipFile' => $zipTask['status'] === 'completed' ? [
                    'filename' => $zipTask['filename'] ?? null,
                    'size' => $zipTask['size'] ?? null,
                ] : null,
                'error' => $zipTask['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to get status', 'STATUS_FAILED', 500);
        }
    }

    /**
     * Download ZIP file
     */
    public function downloadZip(Request $request, string $id, string $token): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        try {
            // Check download PIN if required
            $rawFile = MemoraRawFile::where('uuid', $id)->firstOrFail();

            $settings = $rawFile->settings ?? [];
            $downloadSettings = $settings['download'] ?? [];

            $downloadPinEnabled = $downloadSettings['downloadPinEnabled'] ?? ! empty($downloadSettings['downloadPin'] ?? null);
            $downloadPin = $downloadSettings['downloadPin'] ?? $settings['downloadPin'] ?? null;

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $rawFile->user_uuid === $userUuid;
            }

            if ($downloadPinEnabled && $downloadPin && ! $isOwner) {
                $providedPin = $request->header('X-Download-PIN');
                if (! $providedPin || $providedPin !== $downloadPin) {
                    return ApiResponse::error('Download PIN required', 'DOWNLOAD_PIN_REQUIRED', 401);
                }
            }

            $zipTask = \Illuminate\Support\Facades\Cache::get("raw_file_zip_download_{$token}");

            if (! $zipTask) {
                return ApiResponse::error('Download not found', 'NOT_FOUND', 404);
            }

            if ($zipTask['status'] !== 'completed') {
                return ApiResponse::error('Download not ready', 'NOT_READY', 404);
            }

            if ($zipTask['raw_file_id'] !== $id) {
                return ApiResponse::error('Invalid token', 'INVALID_TOKEN', 403);
            }

            $filePath = storage_path("app/{$zipTask['file_path']}");

            if (! file_exists($filePath)) {
                return ApiResponse::error('File not found', 'FILE_NOT_FOUND', 404);
            }

            $filename = $zipTask['filename'] ?? 'download.zip';

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/zip',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to download raw file ZIP', [
                'token' => $token,
                'raw_file_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Download failed: '.$e->getMessage(), 'DOWNLOAD_FAILED', 500);
        }
    }
}
