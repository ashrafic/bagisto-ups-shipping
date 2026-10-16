<?php

namespace Ashrafic\UpsShipping\Providers;

use Ashrafic\UpsShipping\Client\UpsClient;
use Ashrafic\UpsShipping\Client\UpsOAuth;
use Ashrafic\UpsShipping\Console\Commands\TestUpsCommand;
use Ashrafic\UpsShipping\Console\Commands\ValidateAddressCommand;
use Ashrafic\UpsShipping\Listeners\OrderViewButton;
use Ashrafic\UpsShipping\Services\RateService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class UpsShippingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'ups-shipping');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'ups-shipping');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->listenForViewRenderEvents();
    }

    /**
     * Inject the UPS label button into the admin order view.
     */
    protected function listenForViewRenderEvents(): void
    {
        Event::listen(
            'bagisto.admin.sales.order.page_action.after',
            OrderViewButton::class
        );
    }

    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->registerConfig();

        $this->app->bind(RateService::class, fn () => new RateService(new UpsClient(new UpsOAuth)));

        $this->commands([TestUpsCommand::class, ValidateAddressCommand::class]);
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

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/menu.php', 'menu.admin'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/acl.php', 'acl'
        );
    }
}
