<?php

namespace Ashrafic\UpsShipping\Services;

use Ashrafic\UpsShipping\Client\UpsClient;
use Ashrafic\UpsShipping\Data\TrackingResult;

class TrackingService
{
    /**
     * Create a new service instance.
     */
    public function __construct(protected UpsClient $client) {}

    /**
     * Track a shipment by tracking number.
     */
    public function track(string $trackingNumber): TrackingResult
    {
        $response = $this->client->get('/api/track/v1/details/'.$trackingNumber, [
            'locale' => 'en-US',
        ]);

        return $this->mapResponse($trackingNumber, $response);
    }

    /**
     * Map the tracking response into a normalized result.
     */
    protected function mapResponse(string $trackingNumber, array $response): TrackingResult
    {
        $package = $response['trackResponse']['shipment'][0]['package'][0] ?? [];

        return new TrackingResult(
            trackingNumber: $trackingNumber,
            status: $package['status']['description'] ?? null,
            statusCode: $package['status']['code'] ?? null,
            estimatedDelivery: $this->estimatedDelivery($package),
            events: $this->mapEvents($package['activity'] ?? []),
        );
    }

    /**
     * Resolve the delivery estimate, newest date wins.
     */
    protected function estimatedDelivery(array $package): ?string
    {
        $dates = array_column($package['deliveryDate'] ?? [], 'date');

        return $dates === [] ? null : max($dates);
    }

    /**
     * Normalize activity events, newest first.
     */
    protected function mapEvents(array $activities): array
    {
        $events = array_map(fn (array $activity) => [
            'date' => $activity['date'] ?? null,
            'time' => $activity['time'] ?? null,
            'location' => trim(implode(', ', array_filter([
                $activity['location']['address']['city'] ?? null,
                $activity['location']['address']['state'] ?? null,
                $activity['location']['address']['country'] ?? null,
            ]))),
            'description' => $activity['description'] ?? null,
        ], $activities);

        usort($events, fn (array $left, array $right) => strcmp((string) $right['date'], (string) $left['date']));

        return $events;
    }
}
