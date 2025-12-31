<?php

namespace App\Services\Pricing;

use App\Services\Currency\CurrencyService;

class PricingService
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Get price for a product in a specific currency
     *
     * @return int|null Price in smallest currency unit, null if not available
     */
    public function getPrice(string $productId, string $currency, ?string $region = null): ?int
    {
        // TODO: Query pricing table from database
        // For now, return from config

        $pricing = config('pricing.products', []);

        if (! isset($pricing[$productId])) {
            return null;
        }

        $productPricing = $pricing[$productId];

        // Check region-specific pricing
        if ($region && isset($productPricing['regions'][$region][$currency])) {
            return $productPricing['regions'][$region][$currency];
        }

        // Check currency-specific pricing
        if (isset($productPricing['currencies'][$currency])) {
            return $productPricing['currencies'][$currency];
        }

        // Check base currency and convert
        $baseCurrency = $productPricing['base_currency'] ?? 'USD';
        $basePrice = $productPricing['currencies'][$baseCurrency] ?? null;

        if ($basePrice && $currency !== $baseCurrency) {
            return $this->currencyService->convert($basePrice, $baseCurrency, $currency);
        }

        return $basePrice;
    }

    /**
     * Get formatted price string
     */
    public function getFormattedPrice(string $productId, string $currency, ?string $region = null): ?string
    {
        $price = $this->getPrice($productId, $currency, $region);

        if ($price === null) {
            return null;
        }

        return $this->currencyService->format($price, $currency);
    }
}
