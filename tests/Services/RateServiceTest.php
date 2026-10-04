<?php

use Ashrafic\UpsShipping\Data\CartPackage;
use Ashrafic\UpsShipping\Data\QuotedRate;
use Ashrafic\UpsShipping\Services\RateService;

beforeEach(function () {
    config()->set('carriers.ups.mode', 'sandbox');
    config()->set('carriers.ups.client_id', 'id');
    config()->set('carriers.ups.client_secret', 'secret');
    config()->set('carriers.ups.services', '03,02');
    config()->set('carriers.ups.handling_fee_type', 'fixed');
    config()->set('carriers.ups.handling_fee_amount', '2');
    config()->set('carriers.ups.rate_cache_ttl', '0');

    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response([
            'access_token' => 't', 'expires_in' => 86399,
        ], 200),
    ]);
});

it('maps a shop response into filtered quoted rates with handling fee', function () {
    Http::fake([
        'wwwcie.ups.com/api/rating/v1/Shop' => Http::response([
            'RateResponse' => [
                'RatedShipment' => [
                    [
                        'Service' => ['Code' => '03'],
                        'TotalCharges' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '18.42'],
                    ], [
                        'Service' => ['Code' => '01'],
                        'TotalCharges' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '99.00'],
                    ], [
                        'Service' => ['Code' => '02'],
                        'TotalCharges' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '25.10'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $rates = app(RateService::class)
        ->quote([new CartPackage(weight: 5.0)], 'US', '10001', '90210');

    expect($rates)->toHaveCount(2)
        ->and($rates[0])->toBeInstanceOf(QuotedRate::class)
        ->and($rates[0]->serviceCode)->toBe('03')
        ->and($rates[0]->pricedAmount())->toBe(20.42)
        ->and($rates[1]->serviceCode)->toBe('02')
        ->and($rates[1]->pricedAmount())->toBe(27.10);
});

it('passes the origin into the shipper block', function () {
    Http::fake([
        'wwwcie.ups.com/api/rating/v1/Shop' => Http::response(['RateResponse' => []], 200),
    ]);

    app(RateService::class)->quote(
        [new CartPackage(weight: 5.0)], 'US', '10001', '90210',
        origin: [
            'address1' => '123 Warehouse Way',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'zipcode' => '90001',
            'country' => 'US',
        ],
    );

    Http::assertSent(function ($request) {
        $shipper = $request->data()['RateRequest']['Shipment']['Shipper'] ?? [];

        return ($shipper['Address']['PostalCode'] ?? null) === '90001'
            && ($shipper['Address']['City'] ?? null) === 'Los Angeles'
            && ($shipper['Address']['StateProvinceCode'] ?? null) === 'CA'
            && ($shipper['Address']['AddressLine1'] ?? null) === '123 Warehouse Way'
            && ($shipper['Address']['CountryCode'] ?? null) === 'US';
    });
});

it('sends the shipper number when an account number is configured', function () {
    config()->set('carriers.ups.account_number', 'V8X2A1');

    Http::fake([
        'wwwcie.ups.com/api/rating/v1/Shop' => Http::response(['RateResponse' => []], 200),
    ]);

    app(RateService::class)
        ->quote([new CartPackage(weight: 5.0)], 'US', '10001');

    Http::assertSent(function ($request) {
        return ($request->data()['RateRequest']['Shipment']['Shipper']['ShipperNumber'] ?? null) === 'V8X2A1';
    });
});

it('caches quotes for the configured ttl', function () {
    config()->set('carriers.ups.rate_cache_ttl', '15');

    Http::fake([
        'wwwcie.ups.com/api/rating/v1/Shop' => Http::response([
            'RateResponse' => [
                'RatedShipment' => [
                    ['Service' => ['Code' => '03'], 'TotalCharges' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '18.42']],
                ],
            ],
        ], 200),
    ]);

    $packages = [new CartPackage(weight: 5.0)];
    $service = app(RateService::class);

    $service->quote($packages, 'US', '10001');
    $service->quote($packages, 'US', '10001');

    $shopCalls = 0;

    Http::assertSent(function ($request) use (&$shopCalls) {
        if (str_contains($request->url(), '/api/rating/v1/Shop')) {
            $shopCalls++;
        }

        return true;
    });

    expect($shopCalls)->toBe(1);
});

it('prefers negotiated rates when ups returns them', function () {
    config()->set('carriers.ups.handling_fee_amount', '0');

    Http::fake([
        'wwwcie.ups.com/api/rating/v1/Shop' => Http::response([
            'RateResponse' => [
                'RatedShipment' => [
                    [
                        'Service' => ['Code' => '03'],
                        'TotalCharges' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '18.42'],
                        'NegotiatedRates' => [
                            'NetSummaryCharges' => [
                                'GrandTotal' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '12.10'],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $rates = app(RateService::class)
        ->quote([new CartPackage(weight: 5.0)], 'US', '10001');

    expect($rates[0]->amount)->toBe(18.42)
        ->and($rates[0]->pricedAmount())->toBe(12.10);
});

it('applies percent handling fees on the priced amount', function () {
    config()->set('carriers.ups.handling_fee_type', 'percent');
    config()->set('carriers.ups.handling_fee_amount', '10');

    Http::fake([
        'wwwcie.ups.com/api/rating/v1/Shop' => Http::response([
            'RateResponse' => [
                'RatedShipment' => [
                    ['Service' => ['Code' => '03'], 'TotalCharges' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '20.00']],
                ],
            ],
        ], 200),
    ]);

    $rates = app(RateService::class)
        ->quote([new CartPackage(weight: 5.0)], 'US', '10001');

    expect($rates[0]->pricedAmount())->toBe(22.0);
});

it('returns an empty array when ups reports no applicable services', function () {
    Http::fake([
        'wwwcie.ups.com/api/rating/v1/Shop' => Http::response(['RateResponse' => []], 200),
    ]);

    $rates = app(RateService::class)
        ->quote([new CartPackage(weight: 5.0)], 'US', '10001');

    expect($rates)->toBeArray()->toBeEmpty();
});
