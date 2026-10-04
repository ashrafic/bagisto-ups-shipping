<?php

namespace Ashrafic\UpsShipping\Carriers;

use Ashrafic\UpsShipping\Services\RateService;
use Webkul\Shipping\Carriers\AbstractShipping;

class Ups extends AbstractShipping
{
    /**
     * Shipping method carrier code.
     *
     * @var string
     */
    protected $code = 'ups';

    /**
     * Return shipping rates for the current cart.
     *
     * @return array|false
     */
    public function calculate()
    {
        if (! core()->getConfigData('sales.carriers.ups.active')) {
            return false;
        }

        return app(RateService::class)->rates();
    }
}
