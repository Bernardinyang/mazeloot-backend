<?php

namespace App\Providers;

use App\Services\Currency\CurrencyService;
use App\Services\Currency\ExchangeRateService;
use App\Services\Pagination\PaginationService;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\PaymentService;
use App\Services\Payment\Providers\FlutterwaveProvider;
use App\Services\Payment\Providers\PayPalProvider;
use App\Services\Payment\Providers\PaystackProvider;
use App\Services\Payment\Providers\StripeProvider;
use App\Services\Pricing\PricingService;
use App\Services\Quotas\QuotaService;
use App\Services\Subscription\TierService;
use App\Services\Upload\Contracts\UploadProviderInterface;
use App\Services\Upload\Providers\CloudflareR2Provider;
use App\Services\Upload\Providers\CloudinaryProvider;
use App\Services\Upload\Providers\LocalProvider;
use App\Services\Upload\Providers\S3Provider;
use App\Services\Upload\UploadService;
use Illuminate\Support\ServiceProvider;

class GlobalServicesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Upload Provider based on configuration
        $this->app->bind(UploadProviderInterface::class, function ($app) {
            $provider = config('upload.default_provider', 'local');

            \Illuminate\Support\Facades\Log::info('Upload provider resolved', [
                'provider' => $provider,
                'env_upload_provider' => env('UPLOAD_PROVIDER'),
                'filesystem_disk' => config('filesystems.default'),
            ]);

            return match ($provider) {
                'local' => $app->make(LocalProvider::class),
                's3' => $app->make(S3Provider::class),
                'r2' => $app->make(CloudflareR2Provider::class),
                'cloudinary' => $app->make(CloudinaryProvider::class),
                default => $app->make(LocalProvider::class),
            };
        });

        // Bind Payment Provider based on configuration
        $this->app->bind(PaymentProviderInterface::class, function ($app) {
            $provider = config('payment.default_provider', 'stripe');

            return match ($provider) {
                'stripe' => $app->make(StripeProvider::class),
                'paypal' => $app->make(PayPalProvider::class),
                'paystack' => $app->make(PaystackProvider::class),
                'flutterwave' => $app->make(FlutterwaveProvider::class),
                default => $app->make(StripeProvider::class),
            };
        });

        // Register singleton services
        $this->app->singleton(ExchangeRateService::class);
        $this->app->singleton(CurrencyService::class);
        $this->app->singleton(PricingService::class);
        $this->app->singleton(PaginationService::class);
        $this->app->singleton(TierService::class);
        $this->app->singleton(QuotaService::class);

        // Register UploadService and PaymentService (they depend on providers)
        $this->app->singleton(UploadService::class);
        $this->app->singleton(PaymentService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
