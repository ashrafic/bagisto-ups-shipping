<?php

namespace Ashrafic\UpsShipping\Carriers;

use Webkul\Checkout\Models\CartShippingRate;
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

        return app(\Ashrafic\UpsShipping\Services\RateService::class)->rates();
    }
}
