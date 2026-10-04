<?php

namespace Ashrafic\UpsShipping\Client;

use Ashrafic\UpsShipping\Support\ConfigResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class UpsClient
{
    /**
     * Create a new client instance.
     */
    public function __construct(protected UpsOAuth $oauth, protected ConfigResolver $configResolver = new ConfigResolver) {}

    /**
     * Send a POST request to the UPS API.
     */
    public function post(string $path, array $payload): array
    {
        return $this->send('post', $path, $payload);
    }

    /**
     * Send a GET request to the UPS API.
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, $query);
    }

    /**
     * Dispatch the request with auth and error normalization.
     */
    protected function send(string $method, string $path, array $data): array
    {
        $pending = Http::withToken($this->oauth->token())
            ->timeout(10)
            ->withHeaders([
                'transId' => uniqid('ups-shipping-'),
                'transactionSrc' => 'ashrafic-bagisto-ups-shipping',
            ]);

        $response = match ($method) {
            'get' => $pending->get($this->oauth->baseUrl().$path, $data),
            default => $pending->post($this->oauth->baseUrl().$path, $data),
        };

        $payload = $response->json() ?? [];

        if (! $response->successful()) {
            $this->log('error', 'UPS API failure', ['path' => $path, 'response' => $payload]);

            throw new RuntimeException("UPS API error (HTTP {$response->status()}): ".$this->extractError($payload));
        }

        $this->log('debug', 'UPS API call', ['path' => $path]);

        return $payload;
    }

    /**
     * Pull the first human-readable error from a UPS error envelope.
     */
    protected function extractError(array $payload): string
    {
        return $payload['response']['errors'][0]['message']
            ?? $payload['Fault']['detail']['Errors']['ErrorDetail']['PrimaryErrorCode']['Description']
            ?? $payload['Fault']['detail']['Errors']['ErrorDetail'][0]['PrimaryErrorCode']['Description']
            ?? 'Unknown UPS API error.';
    }

    /**
     * Write to the log when debug mode is enabled.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (! $this->configResolver->get('debug')) {
            return;
        }

        Log::{$level}('[UPS] '.$message, $context);
    }
}
