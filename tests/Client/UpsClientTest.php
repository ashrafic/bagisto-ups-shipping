<?php

use Ashrafic\UpsShipping\Client\UpsClient;
use Ashrafic\UpsShipping\Client\UpsOAuth;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('carriers.ups.mode', 'sandbox');
    config()->set('carriers.ups.client_id', 'id');
    config()->set('carriers.ups.client_secret', 'secret');

    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 86399,
        ], 200),
    ]);
});

it('posts json payloads with bearer auth and returns decoded responses', function () {
    Http::fake([
        'wwwcie.ups.com/api/rating/v1/Shop' => Http::response(['RateResponse' => []], 200),
    ]);

    $client = new UpsClient(new UpsOAuth);

    $response = $client->post('/api/rating/v1/Shop', ['RateRequest' => []]);

    expect($response)->toBeArray();

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('transId')
            && $request->hasHeader('transactionSrc');
    });
});

it('throws with ups error details on failure', function () {
    Http::fake([
        'wwwcie.ups.com/api/rating/v1/Shop' => Http::response([
            'response' => [
                'errors' => [
                    ['code' => '250002', 'message' => 'Invalid Authentication Information'],
                ],
            ],
        ], 401),
    ]);

    $client = new UpsClient(new UpsOAuth);

    expect(fn () => $client->post('/api/rating/v1/Shop', []))
        ->toThrow(RuntimeException::class, 'Invalid Authentication Information');
});
