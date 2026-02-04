<?php

namespace App\Services\Currency;

class CurrencyService
{
    protected ExchangeRateService $exchangeRateService;

    protected array $currencyInfo;

    public function __construct(ExchangeRateService $exchangeRateService)
    {
        $this->exchangeRateService = $exchangeRateService;
        $this->currencyInfo = config('currency.currencies', []);
    }

    /**
     * Convert amount to smallest currency unit (e.g., dollars to cents)
     *
     * @return int Amount in smallest unit
     */
    public function toSmallestUnit(float|int $amount, string $currency): int
    {
        $decimals = $this->getDecimalPlaces($currency);

        return (int) round($amount * (10 ** $decimals));
    }

    /**
     * Convert from smallest currency unit (e.g., cents to dollars)
     *
     * @param  int  $amount  Smallest unit amount
     */
    public function fromSmallestUnit(int $amount, string $currency): float
    {
        $decimals = $this->getDecimalPlaces($currency);

        return $amount / (10 ** $decimals);
    }

    /**
     * Format currency amount
     */
    public function format(int $amountInSmallestUnit, string $currency): string
    {
        $amount = $this->fromSmallestUnit($amountInSmallestUnit, $currency);
        $symbol = $this->getCurrencySymbol($currency);

        return $symbol.number_format($amount, $this->getDecimalPlaces($currency));
    }

    /**
     * Get decimal places for currency
     */
    public function getDecimalPlaces(string $currency): int
    {
        $currency = strtoupper($currency);

        return $this->currencyInfo[$currency]['decimals'] ?? 2;
    }

    /**
     * Get currency symbol
     */
    public function getCurrencySymbol(string $currency): string
    {
        $currency = strtoupper($currency);

        return $this->currencyInfo[$currency]['symbol'] ?? $currency;
    }

    /**
     * Convert amount from one currency to another
     *
     * @return int Amount in smallest unit of target currency
     */
    public function convert(int $amountInSmallestUnit, string $fromCurrency, string $toCurrency): int
    {
        if ($fromCurrency === $toCurrency) {
            return $amountInSmallestUnit;
        }

        $rate = $this->exchangeRateService->getRate($fromCurrency, $toCurrency);
        $fromAmount = $this->fromSmallestUnit($amountInSmallestUnit, $fromCurrency);
        $toAmount = $fromAmount * $rate;

        return $this->toSmallestUnit($toAmount, $toCurrency);
    }

    /**
     * Convert USD cents to target currency smallest unit using the same formula as frontend:
     * rate = convert(100, 'USD', target)/100; return round(usdCents * rate).
     * Keeps order summary in sync with BYO dashboard display.
     *
     * @return int Amount in smallest unit of target currency
     */
    public function convertUsdCentsToTarget(int $usdCents, string $toCurrency): int
    {
        if (strtoupper($toCurrency) === 'USD') {
            return $usdCents;
        }
        $oneUsdInTarget = $this->convert(100, 'USD', $toCurrency);
        $rate = $oneUsdInTarget / 100;

        return (int) round($usdCents * $rate);
    }
}
