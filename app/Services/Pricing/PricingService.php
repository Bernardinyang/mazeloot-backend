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
     * @param  string|null  $userUuid  Optional user UUID to apply early access discounts
     * @return int|null Price in smallest currency unit, null if not available
     */
    public function getPrice(string $productId, string $currency, ?string $region = null, ?string $userUuid = null): ?int
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
            $basePrice = $productPricing['regions'][$region][$currency];
        } elseif (isset($productPricing['currencies'][$currency])) {
            // Check currency-specific pricing
            $basePrice = $productPricing['currencies'][$currency];
        } else {
            // Check base currency and convert
            $baseCurrency = $productPricing['base_currency'] ?? 'USD';
            $basePrice = $productPricing['currencies'][$baseCurrency] ?? null;

            if ($basePrice && $currency !== $baseCurrency) {
                $basePrice = $this->currencyService->convert($basePrice, $baseCurrency, $currency);
            }
        }

        if (! $basePrice) {
            return null;
        }

        // Apply early access discount if user UUID provided
        if ($userUuid) {
            $user = \App\Models\User::with('earlyAccess')->find($userUuid);
            if ($user && $user->hasEarlyAccess()) {
                $discount = $user->getEarlyAccessDiscount($productId);
                if ($discount > 0) {
                    $discountAmount = (int) round($basePrice * ($discount / 100));

                    return $basePrice - $discountAmount;
                }
            }
        }

        return $basePrice;
    }

    /**
     * Get formatted price string
     *
     * @param  string|null  $userUuid  Optional user UUID to apply early access discounts
     */
    public function getFormattedPrice(string $productId, string $currency, ?string $region = null, ?string $userUuid = null): ?string
    {
        $price = $this->getPrice($productId, $currency, $region, $userUuid);

        if ($price === null) {
            return null;
        }

        return $this->currencyService->format($price, $currency);
    }
}
