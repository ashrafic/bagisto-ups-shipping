<?php

namespace Ashrafic\UpsShipping\Carriers;

use Ashrafic\UpsShipping\Data\QuotedRate;
use Ashrafic\UpsShipping\Packing\ItemPacker;
use Ashrafic\UpsShipping\Services\RateService;
use Webkul\Checkout\Facades\Cart;
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
     * Calculate shipping rates for the current cart.
     *
     * @return array|false
     */
    public function calculate()
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $cart = Cart::getCart();

        if (! $cart?->shipping_address) {
            return false;
        }

        $items = $this->cartItems($cart);

        if (empty($items)) {
            return false;
        }

        try {
            $quotes = app(RateService::class)->quote(
                packages: app(ItemPacker::class)->pack($items),
                shipToCountry: (string) $cart->shipping_address->country,
                shipToPostcode: (string) $cart->shipping_address->postcode,
                shipToState: $cart->shipping_address->state,
                origin: $this->origin(),
            );
        } catch (\Throwable $e) {
            logger()->error('[UPS] rate calculation failed: '.$e->getMessage());

            return false;
        }

        return array_map(fn (QuotedRate $quote) => $this->toShippingRate($quote), $quotes);
    }

    /**
     * Extract packable items from the cart.
     */
    protected function cartItems($cart): array
    {
        return $cart->items
            ->filter(fn ($item) => $item->getTypeInstance()->isStockable() && (float) $item->product?->weight > 0)
            ->map(fn ($item) => [
                'weight' => (float) $item->product->weight,
                'quantity' => $item->quantity,
                'store_weight_unit' => core()->getConfigData('general.general.locale_options.weight_unit') ?: 'LBS',
                'length' => $item->product->length ?? null,
                'width' => $item->product->width ?? null,
                'height' => $item->product->height ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Shipper origin from the channel shipping configuration.
     */
    protected function origin(): array
    {
        return [
            'address1' => core()->getConfigData('sales.shipping.origin.address1'),
            'city' => core()->getConfigData('sales.shipping.origin.city'),
            'state' => core()->getConfigData('sales.shipping.origin.state'),
            'zipcode' => core()->getConfigData('sales.shipping.origin.zipcode'),
            'country' => core()->getConfigData('sales.shipping.origin.country'),
        ];
    }

    /**
     * Convert a quoted rate into a Bagisto shipping rate.
     */
    protected function toShippingRate(QuotedRate $quote): CartShippingRate
    {
        $rate = new CartShippingRate;

        $rate->carrier = $this->getCode();
        $rate->carrier_title = $this->getConfigData('title');
        $rate->method = $this->getCode().'_'.$quote->serviceCode;
        $rate->method_title = $quote->serviceLabel;
        $rate->method_description = $this->getConfigData('description');
        $rate->is_calculate_tax = $this->getConfigData('is_calculate_tax');
        $rate->price = core()->convertPrice($quote->pricedAmount());
        $rate->base_price = $quote->pricedAmount();

        return $rate;
    }
}
