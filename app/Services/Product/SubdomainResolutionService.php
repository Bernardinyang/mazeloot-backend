<?php

namespace App\Services\Product;

use App\Models\User;
use App\Domains\Memora\Models\MemoraSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SubdomainResolutionService
{
    /**
     * Resolve user and settings by subdomain or username
     *
     * @param string $subdomainOrUsername
     * @return array{user: User|null, settings: MemoraSettings|null}
     */
    public function resolve(string $subdomainOrUsername): array
    {
        $cacheKey = "subdomain_resolution:{$subdomainOrUsername}";
        
        return Cache::remember($cacheKey, 3600, function () use ($subdomainOrUsername) {
            // Try to find by subdomain first (preferred)
            $memoraProduct = DB::table('products')
                ->where('id', 'memora')
                ->first();
            
            if (!$memoraProduct) {
                return [
                    'user' => null,
                    'settings' => null,
                ];
            }
            
            $preference = DB::table('user_product_preferences')
                ->where('domain', $subdomainOrUsername)
                ->where('product_uuid', $memoraProduct->uuid)
                ->first();

            if ($preference) {
                $user = User::find($preference->user_uuid);
                $settings = $user ? MemoraSettings::where('user_uuid', $user->uuid)->first() : null;
                
                return [
                    'user' => $user,
                    'settings' => $settings,
                ];
            }

            // Fallback: try to find by username (email prefix or similar)
            // For now, we'll use email as username identifier
            // You may need to adjust this based on your username field
            $user = User::where('email', 'like', "{$subdomainOrUsername}@%")
                ->orWhere('first_name', $subdomainOrUsername)
                ->first();

            $settings = $user ? MemoraSettings::where('user_uuid', $user->uuid)->first() : null;

            return [
                'user' => $user,
                'settings' => $settings,
            ];
        });
    }

    /**
     * Clear cache for a subdomain/username
     */
    public function clearCache(string $subdomainOrUsername): void
    {
        Cache::forget("subdomain_resolution:{$subdomainOrUsername}");
    }
}
