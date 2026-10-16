<?php

namespace Ashrafic\UpsShipping\Listeners;

use Webkul\Theme\ViewRenderEventManager;

class OrderViewButton
{
    /**
     * Register the UPS label button on the admin order view.
     */
    public function handle(ViewRenderEventManager $viewRenderEventManager): void
    {
        $order = $viewRenderEventManager->getParam('order');

        if (! core()->getConfigData('sales.carriers.ups.active')) {
            return;
        }

        if (! $order?->shipping_address) {
            return;
        }

        $viewRenderEventManager->addTemplate('ups-shipping::admin.orders.label-button');
    }
}
