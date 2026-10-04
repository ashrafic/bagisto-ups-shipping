<?php

use Ashrafic\UpsShipping\Client\UpsOAuth;
use Illuminate\Support\Facades\Http;

it('fetches and caches an oauth token per mode and credentials', function () {
    config()->set('carriers.ups.mode', 'sandbox');
    config()->set('carriers.ups.client_id', 'test-id');
    config()->set('carriers.ups.client_secret', 'test-secret');

    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response([
            'access_token' => 'token-one',
            'expires_in' => 86399,
        ], 200),
    ]);

    $oauth = new UpsOAuth;

    expect($oauth->token())->toBe('token-one')
        ->and($oauth->token())->toBe('token-one');

    Http::assertSentCount(1);
});

it('targets the production host in production mode', function () {
    config()->set('carriers.ups.mode', 'production');
    config()->set('carriers.ups.client_id', 'prod-id');
    config()->set('carriers.ups.client_secret', 'prod-secret');

    Http::fake([
        'onlinetools.ups.com/security/v1/oauth/token' => Http::response([
            'access_token' => 'token-prod',
            'expires_in' => 86399,
        ], 200),
    ]);

    expect((new UpsOAuth)->token())->toBe('token-prod');
});
