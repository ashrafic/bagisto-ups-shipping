<?php

namespace Ashrafic\UpsShipping\Providers;

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
