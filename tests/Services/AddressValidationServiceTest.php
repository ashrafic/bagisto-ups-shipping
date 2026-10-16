<?php

use Ashrafic\UpsShipping\Client\UpsClient;
use Ashrafic\UpsShipping\Client\UpsOAuth;
use Ashrafic\UpsShipping\Services\AddressValidationService;
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

it('maps a valid address response', function () {
    Http::fake([
        'wwwcie.ups.com/api/addressvalidation/v1/*' => Http::response([
            'XAVResponse' => [
                'ValidAddressIndicator' => 'true',
                'Candidate' => [
                    [
                        'AddressClassification' => ['Code' => '1', 'Description' => 'Commercial'],
                        'Address' => [
                            'AddressLine' => ['200 PARK AVE S'],
                            'PoliticalDivision2' => 'NEW YORK',
                            'PoliticalDivision1' => 'NY',
                            'PostcodePrimaryLow' => '10003',
                            'CountryCode' => 'US',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = (new AddressValidationService(new UpsClient(new UpsOAuth)))->validate([
        'address' => ['200 Park Ave S'],
        'city' => 'New York',
        'state' => 'NY',
        'zipcode' => '10003',
        'country' => 'US',
    ]);

    expect($result['valid'])->toBeTrue()
        ->and($result['classification'])->toBe('Commercial')
        ->and($result['resolved']['city'])->toBe('NEW YORK')
        ->and($result['resolved']['zipcode'])->toBe('10003')
        ->and($result['messages'])->toBe(['Address is valid.']);
});

it('reports when no candidates are found', function () {
    Http::fake([
        'wwwcie.ups.com/api/addressvalidation/v1/*' => Http::response([
            'XAVResponse' => [
                'NoCandidatesIndicator' => 'true',
            ],
        ], 200),
    ]);

    $result = (new AddressValidationService(new UpsClient(new UpsOAuth)))->validate([
        'address' => ['999 Nowhere Ln'],
        'city' => 'Nowhere',
        'state' => 'ZZ',
        'zipcode' => '00000',
        'country' => 'US',
    ]);

    expect($result['valid'])->toBeFalse()
        ->and($result['resolved'])->toBe([])
        ->and($result['messages'])->toBe(['No matching address was found.']);
});

it('targets the regional endpoint for the given country', function () {
    Http::fake([
        'wwwcie.ups.com/api/addressvalidation/v1/*' => Http::response([
            'XAVResponse' => ['ValidAddressIndicator' => 'true'],
        ], 200),
    ]);

    (new AddressValidationService(new UpsClient(new UpsOAuth)))->validate([
        'address' => ['123 Main St'],
        'city' => 'Toronto',
        'state' => 'ON',
        'zipcode' => 'M5V 2T6',
        'country' => 'CA',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/addressvalidation/v1/CA'));
});
