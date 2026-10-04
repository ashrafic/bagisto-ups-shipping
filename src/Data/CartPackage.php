<?php

namespace Ashrafic\UpsShipping\Data;

class CartPackage
{
    /**
     * Create a new package.
     */
    public function __construct(
        public readonly float $weight,
        public readonly int $quantity = 1,
        public readonly ?string $packagingType = null,
        public readonly ?float $length = null,
        public readonly ?float $width = null,
        public readonly ?float $height = null,
    ) {}
}
