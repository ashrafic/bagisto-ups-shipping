<?php

namespace Ashrafic\UpsShipping\Client;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UpsOAuth
{
    /**
     * OAuth token endpoint path.
     *
     * @var string
     */
    public const TOKEN_PATH = '/security/v1/oauth/token';

    /**
     * Fetch a cached access token.
     */
    public function token(): string
    {
        $cacheKey = 'ups-shipping.oauth.'.$this->fingerprint();

        $token = Cache::get($cacheKey);

        if ($token) {
            return $token;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->config('client_id'), $this->config('client_secret'))
            ->post($this->baseUrl().self::TOKEN_PATH, [
                'grant_type' => 'client_credentials',
            ]);

        $payload = $response->json();

        if (! $response->successful() || empty($payload['access_token'])) {
            throw new \RuntimeException('UPS OAuth failed: '.(string) $response->body());
        }

        $ttl = max(60, ((int) ($payload['expires_in'] ?? 0)) - 300);

        Cache::put($cacheKey, $payload['access_token'], $ttl);

        return $payload['access_token'];
    }

    /**
     * Resolve environment base URL from configured mode.
     */
    public function baseUrl(): string
    {
        return $this->config('mode') === 'production'
            ? 'https://onlinetools.ups.com'
            : 'https://wwwcie.ups.com';
    }

    /**
     * Cache fingerprint unique to credentials and mode.
     */
    protected function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            $this->config('mode'),
            $this->config('client_id'),
            $this->config('client_secret'),
        ]));
    }

    /**
     * Read carrier configuration value.
     */
    protected function config(string $key): mixed
    {
        return config('carriers.ups.'.$key);
    }
}
