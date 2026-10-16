<?php

use Ashrafic\UpsShipping\Client\UpsClient;
use Ashrafic\UpsShipping\Client\UpsOAuth;
use Ashrafic\UpsShipping\Data\CartPackage;
use Ashrafic\UpsShipping\Repositories\UpsShipmentRepository;
use Ashrafic\UpsShipping\Services\LabelService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('carriers.ups.mode', 'sandbox');
    config()->set('carriers.ups.client_id', 'id');
    config()->set('carriers.ups.client_secret', 'secret');
    config()->set('carriers.ups.account_number', 'X12345');
    config()->set('carriers.ups.label_disk', 'private');
    config()->set('carriers.ups.debug', false);

    Storage::fake('private');

    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response([
            'access_token' => 't', 'expires_in' => 86399,
        ], 200),
    ]);

    $this->artisan('migrate', ['--realpath' => __DIR__.'/../../src/Database/Migrations'])->run();
});

function fakeUpsShipResponse(): array
{
    return [
        'ShipmentResponse' => [
            'Response' => ['ResponseStatus' => ['Code' => '1']],
            'ShipmentResults' => [
                'ShipmentCharges' => [
                    'TotalCharges' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '18.42'],
                ],
                'TrackingNumber' => '1Z9999999999999999',
                'PackageResults' => [
                    [
                        'TrackingNumber' => '1Z9999999999999999',
                        'Service' => ['Code' => '03'],
                        'LabelImage' => [
                            'LabelImageFormat' => ['Code' => 'PDF'],
                            'GraphicImage' => base64_encode('%PDF-1.4 fake label'),
                        ],
                    ],
                ],
            ],
        ],
    ];
}

function upsLabelService(): LabelService
{
    return new LabelService(
        new UpsClient(new UpsOAuth),
        new UpsShipmentRepository,
    );
}

function upsShipper(): array
{
    return [
        'name' => 'Store',
        'phone' => '5551234567',
        'address' => '1 Main St',
        'city' => 'Farmington',
        'state' => 'NM',
        'zipcode' => '87410',
        'country' => 'US',
    ];
}

function upsRecipient(): array
{
    return [
        'name' => 'Customer',
        'phone' => '5559876543',
        'address' => '200 Park Ave',
        'city' => 'New York',
        'state' => 'NY',
        'zipcode' => '11225',
        'country' => 'US',
    ];
}

it('creates a label, stores the pdf and persists a shipment record', function () {
    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response(['access_token' => 't'], 200),
        'wwwcie.ups.com/api/ship/v1/shipments' => Http::response(fakeUpsShipResponse(), 200),
    ]);

    $shipment = upsLabelService()->create(
        shipper: upsShipper(),
        recipient: upsRecipient(),
        packages: [new CartPackage(weight: 5.0)],
        serviceCode: '03',
    );

    expect($shipment->tracking_number)->toBe('1Z9999999999999999')
        ->and($shipment->service_code)->toBe('03')
        ->and($shipment->status)->toBe('LABEL_CREATED')
        ->and($shipment->label_cost)->toBe('18.4200')
        ->and($shipment->label_currency)->toBe('USD');

    Storage::disk('private')->assertExists('ups-labels/1Z9999999999999999.pdf');

    expect($shipment->raw['ShipmentResponse']['ShipmentResults']['PackageResults'][0]['LabelImage'])
        ->not->toHaveKey('GraphicImage');
});

it('sends the shipper account number and service to the ship api', function () {
    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response(['access_token' => 't'], 200),
        'wwwcie.ups.com/api/ship/v1/shipments' => Http::response(fakeUpsShipResponse(), 200),
    ]);

    upsLabelService()->create(
        shipper: upsShipper(),
        recipient: upsRecipient(),
        packages: [new CartPackage(weight: 5.0, length: 12.0, width: 9.0, height: 6.0)],
        serviceCode: '03',
    );

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'ship/v1/shipments')) {
            return false;
        }

        $shipment = $request['ShipmentRequest']['Shipment'];

        return $shipment['Shipper']['ShipperNumber'] === 'X12345'
            && $shipment['Service']['Code'] === '03'
            && $shipment['Package'][0]['Dimensions']['Length'] === '12'
            && $shipment['LabelSpecification']['LabelImageFormat']['Code'] === 'PDF';
    });
});

it('refuses a label without an account number', function () {
    config()->set('carriers.ups.account_number', null);

    upsLabelService()->create(
        shipper: upsShipper(),
        recipient: upsRecipient(),
        packages: [new CartPackage(weight: 5.0)],
        serviceCode: '03',
    );
})->throws(RuntimeException::class, 'UPS labels require an account number');

it('refuses a label when the store phone is missing', function () {
    $shipper = upsShipper();
    $shipper['phone'] = null;

    upsLabelService()->create(
        shipper: $shipper,
        recipient: upsRecipient(),
        packages: [new CartPackage(weight: 5.0)],
        serviceCode: '03',
    );
})->throws(RuntimeException::class, 'store phone number is missing');

it('voids a label when ups confirms the cancellation', function () {
    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response(['access_token' => 't'], 200),
        'wwwcie.ups.com/api/ship/v1/shipments' => Http::response(fakeUpsShipResponse(), 200),
        'wwwcie.ups.com/api/ship/v1/void' => Http::response([
            'VoidShipmentResponse' => [
                'SummaryResult' => ['Status' => ['Code' => '1', 'Description' => 'Success']],
            ],
        ], 200),
    ]);

    $service = upsLabelService();

    $shipment = $service->create(
        shipper: upsShipper(),
        recipient: upsRecipient(),
        packages: [new CartPackage(weight: 5.0)],
        serviceCode: '03',
    );

    $voided = $service->void($shipment);

    expect($voided->status)->toBe('VOIDED')
        ->and($voided->voided_at)->not->toBeNull();
});

it('keeps the label when ups does not confirm the void', function () {
    Http::fake([
        'wwwcie.ups.com/security/v1/oauth/token' => Http::response(['access_token' => 't'], 200),
        'wwwcie.ups.com/api/ship/v1/shipments' => Http::response(fakeUpsShipResponse(), 200),
        'wwwcie.ups.com/api/ship/v1/void' => Http::response([
            'VoidShipmentResponse' => [
                'SummaryResult' => ['Status' => ['Code' => '0', 'Description' => 'Requested data not found']],
            ],
        ], 200),
    ]);

    $service = upsLabelService();

    $shipment = $service->create(
        shipper: upsShipper(),
        recipient: upsRecipient(),
        packages: [new CartPackage(weight: 5.0)],
        serviceCode: '03',
    );

    $service->void($shipment);
})->throws(RuntimeException::class, 'UPS void was not confirmed');
