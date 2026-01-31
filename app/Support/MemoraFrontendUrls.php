<?php

namespace App\Support;

use App\Domains\Memora\Models\MemoraSettings;

/**
 * Builds frontend (SPA) paths and full URLs for memora public routes.
 * Paths match frontend router: /memora/:domain/proofing/:proofingId, etc.
 * Public :domain must be the creative's branding slug (branding_domain).
 * Use fullUrl() for emails/redirects; use path only for in-app notification action_url.
 */
class MemoraFrontendUrls
{
    private const BASE = '/memora';

    public static function getBrandingDomainForUser(?string $userUuid): ?string
    {
        if (! $userUuid) {
            return null;
        }

        return MemoraSettings::where('user_uuid', $userUuid)->value('branding_domain');
    }

    public static function closureRequestPath(string $token): string
    {
        return self::BASE.'/closure-request/'.$token;
    }

    public static function closureRequestFullUrl(string $token, ?string $baseUrl = null): string
    {
        $base = $baseUrl ?? config('app.frontend_url', config('app.url'));

        return rtrim($base, '/').self::closureRequestPath($token);
    }

    public static function approvalRequestPath(string $token): string
    {
        return self::BASE.'/approval-request/'.$token;
    }

    public static function approvalRequestFullUrl(string $token, ?string $baseUrl = null): string
    {
        $base = $baseUrl ?? config('app.frontend_url', config('app.url'));

        return rtrim($base, '/').self::approvalRequestPath($token);
    }

    /**
     * @param  string|null  $brandingDomain  Creative branding slug (preferred for public URLs)
     * @param  string|null  $projectIdFallback  Fallback when brandingDomain not set
     */
    public static function publicProofingPath(string $proofingId, ?string $brandingDomain = null, ?string $projectIdFallback = null): string
    {
        $domain = $brandingDomain ?? $projectIdFallback ?? $proofingId;

        return self::BASE.'/'.$domain.'/proofing/'.$proofingId;
    }

    public static function publicProofingFullUrl(string $proofingId, ?string $brandingDomain = null, ?string $projectIdFallback = null, ?string $baseUrl = null): string
    {
        $base = $baseUrl ?? config('app.frontend_url', config('app.url'));

        return rtrim($base, '/').self::publicProofingPath($proofingId, $brandingDomain, $projectIdFallback);
    }

    public static function publicCollectionPath(string $domain, string $collectionId): string
    {
        return self::BASE.'/'.$domain.'/collection/'.$collectionId;
    }

    public static function publicCollectionFullUrl(string $domain, string $collectionId, ?string $baseUrl = null): string
    {
        $base = $baseUrl ?? config('app.frontend_url', config('app.url'));

        return rtrim($base, '/').self::publicCollectionPath($domain, $collectionId);
    }

    public static function publicCollectionDownloadPath(string $domain, string $collectionId): string
    {
        return self::BASE.'/'.$domain.'/collection/'.$collectionId.'/download';
    }

    public static function publicCollectionDownloadFullUrl(string $domain, string $collectionId, ?string $baseUrl = null): string
    {
        $base = $baseUrl ?? config('app.frontend_url', config('app.url'));

        return rtrim($base, '/').self::publicCollectionDownloadPath($domain, $collectionId);
    }

    /** Auth app route: proofing detail (for in-app notifications). */
    public static function proofingDetailPath(string $proofingId, ?string $projectId = null): string
    {
        if ($projectId) {
            return '/memora/projects/'.$projectId.'/proofing/'.$proofingId;
        }

        return '/memora/proofing/'.$proofingId;
    }

    /** Auth app route: proofing list (e.g. after delete). */
    public static function proofingListPath(?string $projectId = null): string
    {
        if ($projectId) {
            return '/memora/projects/'.$projectId.'/proofing';
        }

        return '/memora/proofing';
    }

    /** Auth app route: selection detail (for in-app notifications). */
    public static function selectionDetailPath(string $selectionId, ?string $projectId = null): string
    {
        if ($projectId) {
            return self::BASE.'/projects/'.$projectId.'/selections/'.$selectionId;
        }

        return self::BASE.'/selections/'.$selectionId;
    }

    /** Auth app route: selection list (e.g. after delete). */
    public static function selectionListPath(?string $projectId = null): string
    {
        if ($projectId) {
            return self::BASE.'/projects/'.$projectId.'/selections';
        }

        return self::BASE.'/selections';
    }

    /** Auth app route: raw file detail (for in-app notifications). */
    public static function rawFileDetailPath(string $rawFileId, ?string $projectId = null): string
    {
        if ($projectId) {
            return self::BASE.'/projects/'.$projectId.'/raw-files/'.$rawFileId;
        }

        return self::BASE.'/raw-files/'.$rawFileId;
    }

    /** Auth app route: raw file list (e.g. after delete). */
    public static function rawFileListPath(?string $projectId = null): string
    {
        if ($projectId) {
            return self::BASE.'/projects/'.$projectId.'/raw-files';
        }

        return self::BASE.'/raw-files';
    }

    /** Auth app route: collection detail (for in-app notifications). */
    public static function collectionDetailPath(string $collectionId, ?string $projectId = null): string
    {
        if ($projectId) {
            return self::BASE.'/projects/'.$projectId.'/collections/'.$collectionId;
        }

        return self::BASE.'/collections/'.$collectionId;
    }

    /** Auth app route: collection list (e.g. after delete). */
    public static function collectionListPath(?string $projectId = null): string
    {
        if ($projectId) {
            return self::BASE.'/projects/'.$projectId.'/collections';
        }

        return self::BASE.'/collections';
    }

    /** Auth app route: project detail (for in-app notifications). */
    public static function projectDetailPath(string $projectId): string
    {
        return self::BASE.'/projects/'.$projectId;
    }

    /** Auth app route: project list. */
    public static function projectListPath(): string
    {
        return self::BASE.'/projects';
    }
}
