<?php

namespace Ashrafic\UpsShipping\Data;

final class TrackingResult
{
    /**
     * Create a new tracking result.
     *
     * @param  array  $events  each: date, time, location, description
     */
    public function __construct(
        public readonly string $trackingNumber,
        public readonly ?string $status = null,
        public readonly ?string $statusCode = null,
        public readonly ?string $estimatedDelivery = null,
        public readonly array $events = [],
    ) {}
}
