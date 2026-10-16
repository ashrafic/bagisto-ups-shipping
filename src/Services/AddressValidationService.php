<?php

namespace Ashrafic\UpsShipping\Services;

use Ashrafic\UpsShipping\Client\UpsClient;

class AddressValidationService
{
    /**
     * Create a new service instance.
     */
    public function __construct(protected UpsClient $client) {}

    /**
     * Validate an address against the UPS address database.
     *
     * @param  array  $address  address, city, state, zipcode, country
     * @return array{classification: ?string, valid: bool, resolved: array, messages: array<int, string>}
     */
    public function validate(array $address): array
    {
        $country = strtoupper((string) ($address['country'] ?? 'US'));

        $response = $this->client->post('/api/addressvalidation/v1/'.$country.'?maxcandidates=5', [
            'XAVRequest' => [
                'AddressClassification' => ['Code' => '1'],
                'Address' => array_filter([
                    'AddressLine' => array_values(array_filter([(string) ($address['address'][0] ?? $address['address'] ?? '')])),
                    'PoliticalDivision2' => $address['city'] ?? null,
                    'PoliticalDivision1' => $address['state'] ?? null,
                    'PostcodePrimaryLow' => $address['zipcode'] ?? null,
                    'CountryCode' => $country,
                ], fn ($value) => $value !== null && $value !== ''),
                'MaximumList' => '5',
            ],
        ]);

        return $this->mapResponse($response);
    }

    /**
     * Map the XAV response into a normalized result.
     */
    protected function mapResponse(array $response): array
    {
        $xav = $response['XAVResponse'] ?? [];

        $valid = ($xav['ValidAddressIndicator'] ?? null) === 'true';

        $candidate = $xav['Candidate'][0] ?? null;

        return [
            'classification' => $candidate['AddressClassification']['Description']
                ?? $xav['Classification']['Description']
                ?? null,
            'valid' => $valid,
            'resolved' => $candidate !== null ? [
                'address' => $candidate['Address']['AddressLine'] ?? [],
                'city' => $candidate['Address']['PoliticalDivision2'] ?? null,
                'state' => $candidate['Address']['PoliticalDivision1'] ?? null,
                'zipcode' => $candidate['Address']['PostcodePrimaryLow'] ?? null,
                'country' => $candidate['Address']['CountryCode'] ?? null,
            ] : [],
            'messages' => $this->messages($xav, $valid),
        ];
    }

    /**
     * Human-readable notes for the validation outcome.
     *
     * @return array<int, string>
     */
    protected function messages(array $xav, bool $valid): array
    {
        if (isset($xav['NoCandidatesIndicator'])) {
            return ['No matching address was found.'];
        }

        if (! $valid && ! isset($xav['Candidate'])) {
            return ['Address could not be validated.'];
        }

        return $valid ? ['Address is valid.'] : ['Ambiguous match — candidates returned.'];
    }
}
