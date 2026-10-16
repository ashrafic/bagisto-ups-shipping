<?php

namespace Ashrafic\UpsShipping\Services;

use Ashrafic\UpsShipping\Client\UpsClient;
use Ashrafic\UpsShipping\Data\CartPackage;
use Ashrafic\UpsShipping\Models\UpsShipment;
use Ashrafic\UpsShipping\Repositories\UpsShipmentRepository;
use Ashrafic\UpsShipping\Support\ConfigResolver;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LabelService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected UpsClient $client,
        protected UpsShipmentRepository $repository,
        protected ConfigResolver $configResolver = new ConfigResolver,
    ) {}

    /**
     * Create a shipping label for the given shipment data.
     *
     * @param  array  $shipper  name, phone, address, city, state, zipcode, country
     * @param  array  $recipient  name, phone, address, city, state, zipcode, country
     * @param  CartPackage[]  $packages
     */
    public function create(array $shipper, array $recipient, array $packages, string $serviceCode): UpsShipment
    {
        $response = $this->client->post('/api/ship/v1/shipments', [
            'ShipmentRequest' => [
                'Request' => [
                    'RequestOption' => 'validate',
                ],
                'Shipment' => $this->buildShipment($shipper, $recipient, $packages, $serviceCode),
            ],
        ]);

        return $this->store($response);
    }

    /**
     * Void a previously created label.
     */
    public function void(UpsShipment $shipment): UpsShipment
    {
        $response = $this->client->post('/api/ship/v1/void', [
            'VoidShipmentRequest' => [
                'Request' => [
                    'RequestOption' => '',
                ],
                'VoidShipment' => [
                    'ShipmentIdentificationNumber' => $shipment->tracking_number,
                ],
            ],
        ]);

        $status = $response['VoidShipmentResponse']['SummaryResult']['Status']['Code'] ?? null;

        if ($status !== '1') {
            throw new RuntimeException('UPS void was not confirmed: '.(
                $response['VoidShipmentResponse']['SummaryResult']['Status']['Description'] ?? 'unknown reason'
            ));
        }

        return $this->repository->update($shipment, [
            'status' => 'VOIDED',
            'voided_at' => now(),
        ]);
    }

    /**
     * Build the Shipment block of the ship request payload.
     *
     * @param  CartPackage[]  $packages
     */
    protected function buildShipment(array $shipper, array $recipient, array $packages, string $serviceCode): array
    {
        $accountNumber = (string) $this->config('account_number');

        if ($accountNumber === '') {
            throw new RuntimeException('UPS labels require an account number. Set it in admin under Configure → Sales → Shipping Methods → UPS Shipping.');
        }

        return [
            'Description' => 'Bagisto shipment',
            'Shipper' => [
                'Name' => $shipper['name'] ?? 'Store',
                'AttentionName' => $shipper['name'] ?? 'Store',
                'Phone' => ['Number' => $this->phone($shipper, 'store')],
                'ShipperNumber' => $accountNumber,
                'Address' => $this->address($shipper),
            ],
            'ShipTo' => [
                'Name' => $recipient['name'] ?? 'Customer',
                'AttentionName' => $recipient['name'] ?? 'Customer',
                'Phone' => ['Number' => $this->phone($recipient, 'customer')],
                'Address' => $this->address($recipient),
            ],
            'PaymentInformation' => [
                'ShipmentCharge' => [
                    'Type' => '01',
                    'BillShipper' => ['AccountNumber' => $accountNumber],
                ],
            ],
            'Service' => [
                'Code' => $serviceCode,
                'Description' => RateService::SERVICES[$serviceCode] ?? 'Service',
            ],
            'Package' => array_map(fn (CartPackage $package) => $this->package($package), $packages),
            'LabelSpecification' => [
                'LabelImageFormat' => ['Code' => 'PDF'],
                'LabelStockSize' => ['Height' => '6', 'Width' => '4'],
            ],
        ];
    }

    /**
     * Resolve a contact phone, failing with an actionable message.
     */
    protected function phone(array $data, string $role): string
    {
        if (empty($data['phone'])) {
            throw new RuntimeException("The {$role} phone number is missing. ".(
                $role === 'store'
                    ? 'Set it in admin under Configure → Sales → Shipping Settings → Origin (Contact Number).'
                    : 'The order address needs a phone number.'
            ));
        }

        return preg_replace('/\D+/', '', (string) $data['phone']);
    }

    /**
     * Build one package block, with dimensions when available.
     */
    protected function package(CartPackage $package): array
    {
        $block = [
            'Packaging' => [
                'Code' => $package->packagingType ?? $this->config('packaging_type', '02'),
            ],
            'PackageWeight' => [
                'UnitOfMeasurement' => ['Code' => $this->config('weight_unit', 'LBS')],
                'Weight' => (string) round($package->weight, 2),
            ],
        ];

        if ($package->length && $package->width && $package->height) {
            $block['Dimensions'] = [
                'UnitOfMeasurement' => ['Code' => $this->config('weight_unit', 'LBS') === 'KGS' ? 'CM' : 'IN'],
                'Length' => (string) round($package->length, 2),
                'Width' => (string) round($package->width, 2),
                'Height' => (string) round($package->height, 2),
            ];
        }

        return $block;
    }

    /**
     * UPS address block from origin-style key/value data.
     */
    protected function address(array $data): array
    {
        return array_filter([
            'AddressLine' => isset($data['address']) && $data['address'] !== '' ? [$data['address']] : null,
            'City' => $data['city'] ?? null,
            'StateProvinceCode' => $data['state'] ?? null,
            'PostalCode' => $data['zipcode'] ?? null,
            'CountryCode' => $data['country'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Read carrier configuration value.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->configResolver->get($key, $default);
    }

    /**
     * Persist the label document and shipment record from the response.
     */
    protected function store(array $response): UpsShipment
    {
        $results = $response['ShipmentResponse']['ShipmentResults'] ?? [];

        $trackingNumber = $results['TrackingNumber']
            ?? throw new RuntimeException('UPS label response did not contain a tracking number.');

        $document = $results['PackageResults'][0]['LabelImage']['GraphicImage']
            ?? throw new RuntimeException('UPS label response did not contain a label document.');

        $path = 'ups-labels/'.$trackingNumber.'.pdf';

        Storage::disk($this->config('label_disk', 'private'))
            ->put($path, base64_decode($document));

        try {
            return $this->repository->create([
                'tracking_number' => $trackingNumber,
                'service_code' => $results['PackageResults'][0]['Service']['Code'] ?? null,
                'status' => 'LABEL_CREATED',
                'label_path' => $path,
                'label_cost' => isset($results['ShipmentCharges']['TotalCharges']['MonetaryValue'])
                    ? round((float) $results['ShipmentCharges']['TotalCharges']['MonetaryValue'], 2)
                    : null,
                'label_currency' => $results['ShipmentCharges']['TotalCharges']['CurrencyCode'] ?? null,
                'raw' => $this->stripDocuments($response),
            ]);
        } catch (\Throwable $e) {
            Storage::disk($this->config('label_disk', 'private'))->delete($path);

            throw $e;
        }
    }

    /**
     * Remove label images from the response before persisting it.
     */
    protected function stripDocuments(array $response): array
    {
        foreach ($response['ShipmentResponse']['ShipmentResults']['PackageResults'] ?? [] as $index => $package) {
            unset($response['ShipmentResponse']['ShipmentResults']['PackageResults'][$index]['LabelImage']['GraphicImage']);
        }

        return $response;
    }
}
