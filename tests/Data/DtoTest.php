<?php

use Ashrafic\UpsShipping\Data\CartPackage;
use Ashrafic\UpsShipping\Data\QuotedRate;

it('builds a cart package and exposes its weight', function () {
    $package = new CartPackage(
        weight: 12.5,
        quantity: 2,
        packagingType: '02',
    );

    expect($package->weight)->toBe(12.5)
        ->and($package->quantity)->toBe(2)
        ->and($package->packagingType)->toBe('02');
});

it('builds a quoted rate with optional negotiated amount', function () {
    $rate = new QuotedRate(
        serviceCode: '03',
        serviceLabel: 'Ground',
        currency: 'USD',
        amount: 18.42,
    );

    expect($rate->negotiatedAmount)->toBeNull();

    $negotiated = new QuotedRate(
        serviceCode: '03',
        serviceLabel: 'Ground',
        currency: 'USD',
        amount: 18.42,
        negotiatedAmount: 12.10,
    );

    expect($negotiated->negotiatedAmount)->toBe(12.10);
});
