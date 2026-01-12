<?php

namespace App\Support\Traits;

trait ExtractsRouteParameters
{
    protected function getRouteParameter(\Illuminate\Http\Request $request, string $key, ?string $fallback = null): ?string
    {
        return $request->route($key) ?? $fallback;
    }
}
