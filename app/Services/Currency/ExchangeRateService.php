<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Cache;

class ExchangeRateService
{
    /**
     * Get exchange rate between two currencies
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float Exchange rate
     */
    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = "exchange_rate:{$fromCurrency}:{$toCurrency}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($fromCurrency, $toCurrency) {
            // TODO: Integrate with exchange rate API (e.g., exchangerate-api.com, fixer.io)
            // For now, return mock rates or fetch from config
            
            $rates = config('currency.exchange_rates', []);
            $key = strtoupper($fromCurrency) . '_' . strtoupper($toCurrency);
            
            return $rates[$key] ?? 1.0;
        });
    }

    /**
     * Update exchange rates (would call external API)
     *
     * @return void
     */
    public function updateRates(): void
    {
        // TODO: Implement exchange rate fetching from external API
        // This would fetch current rates and cache them
    }
}
