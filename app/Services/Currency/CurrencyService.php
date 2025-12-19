<?php

namespace App\Services\Currency;

use App\Services\Currency\ExchangeRateService;

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
     * @param float|int $amount
     * @param string $currency
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
     * @param int $amount Smallest unit amount
     * @param string $currency
     * @return float
     */
    public function fromSmallestUnit(int $amount, string $currency): float
    {
        $decimals = $this->getDecimalPlaces($currency);
        return $amount / (10 ** $decimals);
    }

    /**
     * Format currency amount
     *
     * @param int $amountInSmallestUnit
     * @param string $currency
     * @return string
     */
    public function format(int $amountInSmallestUnit, string $currency): string
    {
        $amount = $this->fromSmallestUnit($amountInSmallestUnit, $currency);
        $symbol = $this->getCurrencySymbol($currency);
        
        return $symbol . number_format($amount, $this->getDecimalPlaces($currency));
    }

    /**
     * Get decimal places for currency
     *
     * @param string $currency
     * @return int
     */
    public function getDecimalPlaces(string $currency): int
    {
        $currency = strtoupper($currency);
        return $this->currencyInfo[$currency]['decimals'] ?? 2;
    }

    /**
     * Get currency symbol
     *
     * @param string $currency
     * @return string
     */
    public function getCurrencySymbol(string $currency): string
    {
        $currency = strtoupper($currency);
        return $this->currencyInfo[$currency]['symbol'] ?? $currency;
    }

    /**
     * Convert amount from one currency to another
     *
     * @param int $amountInSmallestUnit
     * @param string $fromCurrency
     * @param string $toCurrency
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
}
