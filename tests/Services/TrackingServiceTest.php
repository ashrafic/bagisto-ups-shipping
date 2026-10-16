<?php

use Ashrafic\UpsShipping\Client\UpsClient;
use Ashrafic\UpsShipping\Client\UpsOAuth;
use Ashrafic\UpsShipping\Services\TrackingService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('carriers.ups.mode', 'sandbox');
    config()->set('carriers.ups.client_id', 'id');
    config()->set('carriers.ups.client_secret', 'secret');
    config()->set('carriers.ups.debug', false);

    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response([
            'access_token' => 't', 'expires_in' => 86399,
        ], 200),
    ]);
});

it('maps the track response into a normalized result', function () {
    Http::fake([
        'wwwcie.ups.com/api/track/v1/details/*' => Http::response([
            'trackResponse' => [
                'shipment' => [
                    [
                        'package' => [
                            [
                                'trackingNumber' => '1Z9999999999999999',
                                'deliveryDate' => [['date' => '20261018']],
                                'status' => ['code' => 'I', 'description' => 'In Transit'],
                                'activity' => [
                                    [
                                        'date' => '20261015',
                                        'time' => '140000',
                                        'location' => ['address' => ['city' => 'New York', 'state' => 'NY', 'country' => 'US']],
                                        'description' => 'Origin Scan',
                                    ], [
                                        'date' => '20261016',
                                        'time' => '090000',
                                        'location' => ['address' => ['city' => 'Brooklyn', 'state' => 'NY', 'country' => 'US']],
                                        'description' => 'Arrival Scan',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = (new TrackingService(new UpsClient(new UpsOAuth)))->track('1Z9999999999999999');

    expect($result->trackingNumber)->toBe('1Z9999999999999999')
        ->and($result->status)->toBe('In Transit')
        ->and($result->statusCode)->toBe('I')
        ->and($result->estimatedDelivery)->toBe('20261018')
        ->and($result->events)->toHaveCount(2)
        ->and($result->events[0]['date'])->toBe('20261016')
        ->and($result->events[0]['location'])->toBe('Brooklyn, NY, US');
});

it('requests the tracking details endpoint with the locale', function () {
    Http::fake([
        'wwwcie.ups.com/api/track/v1/details/*' => Http::response([
            'trackResponse' => ['shipment' => [['package' => [['status' => ['description' => 'Delivered']]]]]],
        ], 200),
    ]);

    (new TrackingService(new UpsClient(new UpsOAuth)))->track('1Z9999999999999999');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/track/v1/details/1Z9999999999999999')
        && str_contains($request->url(), 'locale=en-US'));
});
