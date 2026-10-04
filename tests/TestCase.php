<?php

namespace Ashrafic\UpsShipping\Tests;

use Ashrafic\UpsShipping\Providers\UpsShippingServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Load package service providers.
     *
     * @param  Application  $app
     */
    protected function getPackageProviders($app): array
    {
        return [UpsShippingServiceProvider::class];
    }
}
