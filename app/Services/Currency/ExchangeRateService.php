<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    protected string $cacheKey = 'exchange_rates:usd';

    protected int $cacheTtlSeconds = 43200; // 12 hours

    /**
     * Get exchange rate between two currencies
     *
     * @return float Exchange rate
     */
    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if ($from === $to) {
            return 1.0;
        }

        $rates = $this->fetchRates();

        $fromRate = $rates[$from] ?? null;
        $toRate = $rates[$to] ?? null;

        if ($fromRate === null || $toRate === null) {
            $configKey = "{$from}_{$to}";
            $fallback = config("currency.exchange_rates.{$configKey}");

            if ($fallback !== null) {
                return (float) $fallback;
            }

            $inverse = config("currency.exchange_rates.{$to}_{$from}");
            if ($inverse !== null && $inverse > 0) {
                return 1.0 / (float) $inverse;
            }

            Log::warning("Exchange rate not found for {$from}->{$to}, using 1.0");

            return 1.0;
        }

        return (float) ($toRate / $fromRate);
    }

    /**
     * Fetch USD-base rates from external API, cached
     *
     * @return array<string, float>
     */
    protected function fetchRates(): array
    {
        return Cache::remember($this->cacheKey, $this->cacheTtlSeconds, function () {
            $provider = config('currency.provider', 'exchangerate');

            if ($provider === 'frankfurter') {
                return $this->fetchFromFrankfurter();
            }

            return $this->fetchFromExchangerate();
        });
    }

    /**
     * Fetch from Frankfurter (ECB reference rates)
     *
     * @return array<string, float>
     */
    protected function fetchFromFrankfurter(): array
    {
        $url = config('currency.frankfurter_url', 'https://api.frankfurter.dev/v1/latest');
        $response = Http::timeout(10)->get($url, ['base' => 'USD']);

        if (! $response->successful()) {
            Log::warning('Frankfurter API failed', ['status' => $response->status()]);

            return $this->getConfigRates();
        }

        $data = $response->json();
        $rates = $data['rates'] ?? [];

        if (empty($rates)) {
            return $this->getConfigRates();
        }

        $rates['USD'] = 1.0;

        return array_map('floatval', $rates);
    }

    /**
     * Fetch from exchangerate-api (open endpoint, 165+ currencies)
     *
     * @return array<string, float>
     */
    protected function fetchFromExchangerate(): array
    {
        $url = config('currency.exchangerate_url', 'https://open.er-api.com/v6/latest');
        $response = Http::timeout(10)->get($url, ['base' => 'USD']);

        if (! $response->successful()) {
            Log::warning('Exchangerate API failed', ['status' => $response->status()]);

            return $this->getConfigRates();
        }

        $data = $response->json();
        if (($data['result'] ?? '') !== 'success') {
            return $this->getConfigRates();
        }

        $rates = $data['rates'] ?? [];

        if (empty($rates)) {
            return $this->getConfigRates();
        }

        return array_map('floatval', $rates);
    }

    /**
     * Build rates array from config fallback
     *
     * @return array<string, float>
     */
    protected function getConfigRates(): array
    {
        $config = config('currency.exchange_rates', []);
        $rates = ['USD' => 1.0];

        foreach ($config as $key => $value) {
            if (str_starts_with($key, 'USD_')) {
                $to = substr($key, 4);
                $rates[$to] = (float) $value;
            }
        }

        return $rates;
    }

    /**
     * Clear cached rates (e.g. for manual refresh)
     */
    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
    }
}
