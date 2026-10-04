<?php

namespace Ashrafic\UpsShipping\Services;

use Ashrafic\UpsShipping\Client\UpsClient;
use Ashrafic\UpsShipping\Data\CartPackage;
use Ashrafic\UpsShipping\Data\QuotedRate;
use Illuminate\Support\Facades\Cache;

class RateService
{
    /**
     * UPS service code to label map.
     *
     * @var array
     */
    public const SERVICES = [
        '01' => 'Next Day Air',
        '02' => '2nd Day Air',
        '03' => 'Ground',
        '07' => 'Worldwide Express',
        '08' => 'Worldwide Expedited',
        '11' => 'Standard',
        '12' => '3 Day Select',
        '13' => 'Next Day Air Saver',
        '14' => 'Next Day Air Early',
        '54' => 'Worldwide Express Plus',
        '59' => '2nd Day Air AM',
        '65' => 'Worldwide Saver',
    ];

    /**
     * Create a new service instance.
     */
    public function __construct(protected UpsClient $client) {}

    /**
     * Quote the given packages, returning allowed services only.
     *
     * @param  CartPackage[]  $packages
     * @param  array  $origin  optional shipper origin: address1, city, state, zipcode, country
     * @return QuotedRate[]
     */
    public function quote(array $packages, string $shipToCountry, string $shipToPostcode, ?string $shipToState = null, ?array $origin = null): array
    {
        $cacheKey = 'ups-shipping.rates.'.md5(serialize($packages).'|'.$shipToCountry.'|'.$shipToPostcode.'|'.(string) $shipToState.'|'.(string) json_encode($origin));

        $ttl = (int) config('carriers.ups.rate_cache_ttl', 0);

        if ($ttl > 0 && $cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $payload = $this->buildShopRequest($packages, $shipToCountry, $shipToPostcode, $shipToState, $origin);

        $response = $this->client->post('/api/rating/v1/Shop', $payload);

        $rates = $this->mapResponse($response);

        if ($ttl > 0) {
            Cache::put($cacheKey, $rates, now()->addMinutes($ttl));
        }

        return $rates;
    }

    /**
     * Build the Rating API Shop request payload.
     *
     * @param  CartPackage[]  $packages
     */
    protected function buildShopRequest(array $packages, string $shipToCountry, string $shipToPostcode, ?string $shipToState, ?array $origin): array
    {
        return [
            'RateRequest' => [
                'Request' => [
                    'RequestOption' => 'Shop',
                ],
                'Shipment' => [
                    'Shipper' => $this->shipper($origin),
                    'ShipTo' => [
                        'Address' => array_filter([
                            'StateProvinceCode' => $shipToState,
                            'PostalCode' => $shipToPostcode,
                            'CountryCode' => $shipToCountry,
                        ]),
                    ],
                    'Package' => array_map(fn (CartPackage $package) => [
                        'PackagingType' => [
                            'Code' => $package->packagingType ?? config('carriers.ups.packaging_type', '02'),
                        ],
                        'PackageWeight' => [
                            'UnitOfMeasurement' => ['Code' => config('carriers.ups.weight_unit', 'LBS')],
                            'Weight' => (string) $package->weight,
                        ],
                    ], $packages),
                ],
            ],
        ];
    }

    /**
     * Shipper block from the given origin.
     */
    protected function shipper(?array $origin): array
    {
        $shipper = [
            'Name' => 'Store',
            'Address' => array_filter([
                'AddressLine1' => $origin['address1'] ?? null,
                'City' => $origin['city'] ?? null,
                'StateProvinceCode' => $origin['state'] ?? null,
                'PostalCode' => $origin['zipcode'] ?? null,
                'CountryCode' => $origin['country'] ?? null,
            ]),
        ];

        if ($accountNumber = config('carriers.ups.account_number')) {
            $shipper['ShipperNumber'] = $accountNumber;
        }

        return $shipper;
    }

    /**
     * Map RatedShipment entries to filtered QuotedRate objects.
     */
    protected function mapResponse(array $response): array
    {
        $allowed = array_filter(explode(',', (string) config('carriers.ups.services', '')));
        $allowed = $allowed ?: array_keys(self::SERVICES);

        $rates = [];

        foreach ($response['RateResponse']['RatedShipment'] ?? [] as $shipment) {
            $code = $shipment['Service']['Code'] ?? null;

            if (! $code || ! in_array($code, $allowed) || ! isset(self::SERVICES[$code])) {
                continue;
            }

            $rates[] = new QuotedRate(
                serviceCode: $code,
                serviceLabel: self::SERVICES[$code],
                currency: $shipment['TotalCharges']['CurrencyCode'] ?? 'USD',
                amount: (float) $shipment['TotalCharges']['MonetaryValue'],
                negotiatedAmount: isset($shipment['NegotiatedRates']['NetSummaryCharges']['GrandTotal']['MonetaryValue'])
                    ? (float) $shipment['NegotiatedRates']['NetSummaryCharges']['GrandTotal']['MonetaryValue']
                    : null,
            );
        }

        return $this->applyHandlingFee($rates);
    }

    /**
     * Apply the configured handling fee to every rate.
     */
    protected function applyHandlingFee(array $rates): array
    {
        $type = config('carriers.ups.handling_fee_type', 'fixed');
        $amount = (float) config('carriers.ups.handling_fee_amount', 0);

        if ($amount <= 0) {
            return $rates;
        }

        return array_map(function (QuotedRate $rate) use ($type, $amount) {
            $fee = $type === 'percent' ? $rate->pricedAmount() * ($amount / 100) : $amount;

            return new QuotedRate(
                serviceCode: $rate->serviceCode,
                serviceLabel: $rate->serviceLabel,
                currency: $rate->currency,
                amount: round($rate->amount + $fee, 2),
                negotiatedAmount: $rate->negotiatedAmount !== null ? round($rate->negotiatedAmount + $fee, 2) : null,
            );
        }, $rates);
    }
}
