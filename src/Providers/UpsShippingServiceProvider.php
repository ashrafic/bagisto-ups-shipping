<?php

namespace Ashrafic\UpsShipping\Providers;

use Ashrafic\UpsShipping\Client\UpsClient;
use Ashrafic\UpsShipping\Client\UpsOAuth;
use Ashrafic\UpsShipping\Services\RateService;
use Illuminate\Support\ServiceProvider;

class UpsShippingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'ups-shipping');
    }

    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->registerConfig();

        $this->app->bind(RateService::class, fn () => new RateService(new UpsClient(new UpsOAuth)));
    }

    /**
     * Register package configuration.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/carriers.php', 'carriers'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/system.php', 'core'
        );
    }
}
