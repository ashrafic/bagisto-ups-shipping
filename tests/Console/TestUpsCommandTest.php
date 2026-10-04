<?php

use Illuminate\Support\Facades\Http;

it('prints a rate table on success', function () {
    config()->set('carriers.ups.mode', 'sandbox');
    config()->set('carriers.ups.client_id', 'id');
    config()->set('carriers.ups.client_secret', 'secret');
    config()->set('carriers.ups.services', '03');
    config()->set('carriers.ups.rate_cache_ttl', '0');

    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response([
            'access_token' => 't', 'expires_in' => 86399,
        ], 200),
        'wwwcie.ups.com/api/rating/v1/Shop' => Http::response([
            'RateResponse' => ['RatedShipment' => [
                ['Service' => ['Code' => '03'], 'TotalCharges' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '18.42']],
            ]],
        ], 200),
    ]);

    $this->artisan('ups:test')->assertSuccessful();
});

it('fails gracefully when ups rejects the request', function () {
    config()->set('carriers.ups.mode', 'sandbox');
    config()->set('carriers.ups.client_id', 'id');
    config()->set('carriers.ups.client_secret', 'secret');
    config()->set('carriers.ups.rate_cache_ttl', '0');

    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response(
            ['error' => 'invalid_client'], 401
        ),
    ]);

    $this->artisan('ups:test')->assertFailed();
});
