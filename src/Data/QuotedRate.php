<?php

namespace Ashrafic\UpsShipping\Data;

class QuotedRate
{
    /**
     * Create a new quoted rate.
     */
    public function __construct(
        public readonly string $serviceCode,
        public readonly string $serviceLabel,
        public readonly string $currency,
        public readonly float $amount,
        public readonly ?float $negotiatedAmount = null,
    ) {}

    /**
     * Priced amount — negotiated rate when available.
     */
    public function pricedAmount(): float
    {
        return $this->negotiatedAmount ?? $this->amount;
    }
}
