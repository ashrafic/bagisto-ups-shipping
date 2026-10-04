<?php

namespace Ashrafic\UpsShipping\Packing;

use Ashrafic\UpsShipping\Data\CartPackage;

interface PackerInterface
{
    /**
     * Pack cart items into shipment packages.
     *
     * @param  array  $items  each: weight (store unit), quantity, store_weight_unit, optional dimensions
     * @return CartPackage[]
     */
    public function pack(array $items): array;
}
