<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraRawFiles;
use App\Domains\Memora\Resources\V1\MediaSetResource;
use App\Domains\Memora\Resources\V1\RawFilesResource;
use App\Domains\Memora\Services\MediaSetService;
use App\Http\Controllers\Controller;
use App\Services\Product\SubdomainResolutionService;
use App\Support\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicRawFilesController extends Controller
{
    protected MediaSetService $mediaSetService;
    protected SubdomainResolutionService $subdomainResolutionService;

    public function __construct(MediaSetService $mediaSetService, SubdomainResolutionService $subdomainResolutionService)
    {
        $this->mediaSetService = $mediaSetService;
        $this->subdomainResolutionService = $subdomainResolutionService;
    }

    protected function resolveUserAndValidateRawFiles(string $subdomainOrUsername, string $rawFilesId): array
    {
        $resolution = $this->subdomainResolutionService->resolve($subdomainOrUsername);
        $resolvedUser = $resolution['user'];

        if (!$resolvedUser) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('User not found');
        }

        $rawFiles = MemoraRawFiles::where('uuid', $rawFilesId)
            ->where('user_uuid', $resolvedUser->uuid)
            ->firstOrFail();

        return ['user' => $resolvedUser, 'rawFiles' => $rawFiles];
    }

    public function checkStatus(Request $request, string $subdomainOrUsername, string $id): JsonResponse
    {
        $subdomainOrUsername = $request->route('subdomainOrUsername') ?? $subdomainOrUsername;
        $id = $request->route('id') ?? $id;
        
        try {
            $result = $this->resolveUserAndValidateRawFiles($subdomainOrUsername, $id);
            $rawFiles = $result['rawFiles'];

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $rawFiles->user_uuid === $userUuid;
            }

            $settings = $rawFiles->settings ?? [];
            $hasPassword = ! empty($rawFiles->getAttribute('password'));
            $downloadPinEnabled = $settings['download']['downloadPinEnabled'] ?? false;
            $allowedEmails = $rawFiles->allowed_emails ?? [];

            return ApiResponse::success([
                'id' => $rawFiles->uuid,
                'status' => $rawFiles->status?->value ?? $rawFiles->status,
                'name' => $rawFiles->name,
                'isOwner' => $isOwner,
                'hasPassword' => $hasPassword,
                'downloadPinEnabled' => $downloadPinEnabled,
                'allowedEmails' => $allowedEmails,
                'isAccessible' => ($rawFiles->status?->value === 'active' || $rawFiles->status === 'active') || (($rawFiles->status?->value === 'draft' || $rawFiles->status === 'draft') && $isOwner),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw Files phase not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to check raw files status', [
                'raw_files_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to check status', 'CHECK_FAILED', 500);
        }
    }

    public function verifyPassword(Request $request, string $subdomainOrUsername, string $id): JsonResponse
    {
        $subdomainOrUsername = $request->route('subdomainOrUsername') ?? $subdomainOrUsername;
        $id = $request->route('id') ?? $id;
        
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->resolveUserAndValidateRawFiles($subdomainOrUsername, $id);
            $rawFiles = $result['rawFiles'];

            $password = $rawFiles->getAttribute('password');

            if (! $password) {
                return ApiResponse::success(['verified' => true]);
            }

            $verified = $request->input('password') === $password;

            return ApiResponse::success(['verified' => $verified]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw Files phase not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to verify password', [
                'raw_files_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to verify password', 'VERIFY_FAILED', 500);
        }
    }

    public function verifyDownloadPin(Request $request, string $subdomainOrUsername, string $id): JsonResponse
    {
        $subdomainOrUsername = $request->route('subdomainOrUsername') ?? $subdomainOrUsername;
        $id = $request->route('id') ?? $id;
        
        $request->validate([
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ]);

        try {
            $result = $this->resolveUserAndValidateRawFiles($subdomainOrUsername, $id);
            $rawFiles = $result['rawFiles'];

            $status = $rawFiles->status?->value ?? $rawFiles->status;

            if ($status !== 'active') {
                return ApiResponse::error('Raw Files phase is not accessible', 'RAW_FILES_NOT_ACCESSIBLE', 403);
            }

            $settings = $rawFiles->settings ?? [];
            $downloadPin = $settings['download']['downloadPin'] ?? null;
            $downloadPinEnabled = $settings['download']['downloadPinEnabled'] ?? false;

            if (! $downloadPinEnabled || ! $downloadPin) {
                return ApiResponse::success(['verified' => true]);
            }

            $verified = $request->input('pin') === $downloadPin;

            return ApiResponse::success(['verified' => $verified]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw Files phase not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to verify download PIN', [
                'raw_files_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to verify download PIN', 'VERIFY_FAILED', 500);
        }
    }

    public function show(Request $request, string $subdomainOrUsername, string $id): JsonResponse
    {
        $subdomainOrUsername = $request->route('subdomainOrUsername') ?? $subdomainOrUsername;
        $id = $request->route('id') ?? $id;
        
        try {
            $resolution = $this->subdomainResolutionService->resolve($subdomainOrUsername);
            $resolvedUser = $resolution['user'];

            if (!$resolvedUser) {
                return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
            }

            $rawFiles = MemoraRawFiles::query()
                ->where('uuid', $id)
                ->where('user_uuid', $resolvedUser->uuid)
                ->with(['mediaSets' => function ($query) {
                    $query->withCount('media')->orderBy('order');
                }])
                ->firstOrFail();

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $rawFiles->user_uuid === $userUuid;
            }

            $status = $rawFiles->status?->value ?? $rawFiles->status;

            if ($status !== 'active' && ! ($status === 'draft' && $isOwner)) {
                return ApiResponse::error('Raw Files phase is not accessible', 'RAW_FILES_NOT_ACCESSIBLE', 403);
            }

            $settings = $rawFiles->settings ?? [];
            $hasPasswordProtection = ! empty($rawFiles->getAttribute('password'));
            $password = $rawFiles->getAttribute('password');

            if ($hasPasswordProtection && $password && ! $isOwner) {
                $providedPassword = $request->header('X-Raw-Files-Password');
                if (! $providedPassword || $providedPassword !== $password) {
                    return ApiResponse::error('Password required', 'PASSWORD_REQUIRED', 401);
                }
            }

            if ($isOwner) {
                return ApiResponse::success(new RawFilesResource($rawFiles));
            }

            $publicRawFiles = clone $rawFiles;
            $publicRawFiles->makeHidden(['password']);
            if (isset($publicRawFiles->settings['download']['downloadPin'])) {
                unset($publicRawFiles->settings['download']['downloadPin']);
            }

            return ApiResponse::success(new RawFilesResource($publicRawFiles));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw Files phase not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch raw files phase', [
                'raw_files_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch raw files phase', 'FETCH_FAILED', 500);
        }
    }

    public function getSets(Request $request, string $subdomainOrUsername, string $id): JsonResponse
    {
        $subdomainOrUsername = $request->route('subdomainOrUsername') ?? $subdomainOrUsername;
        $id = $request->route('id') ?? $id;
        
        try {
            $result = $this->resolveUserAndValidateRawFiles($subdomainOrUsername, $id);
            $rawFiles = $result['rawFiles'];

            $status = $rawFiles->status?->value ?? $rawFiles->status;

            if ($status !== 'active') {
                return ApiResponse::error('Raw Files phase is not accessible', 'RAW_FILES_NOT_ACCESSIBLE', 403);
            }

            $sets = \App\Domains\Memora\Models\MemoraMediaSet::where('raw_files_uuid', $id)
                ->withCount(['media' => function ($query) {
                    $query->whereNull('deleted_at');
                }])
                ->orderBy('order')
                ->get();

            return ApiResponse::success(MediaSetResource::collection($sets));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw Files phase not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch sets', [
                'raw_files_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch sets', 'FETCH_FAILED', 500);
        }
    }
}
