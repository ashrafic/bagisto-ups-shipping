<?php

namespace Ashrafic\UpsShipping\Console\Commands;

use Ashrafic\UpsShipping\Data\CartPackage;
use Ashrafic\UpsShipping\Services\RateService;
use Ashrafic\UpsShipping\Support\ConfigResolver;
use Illuminate\Console\Command;
use Throwable;

class TestUpsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ups:test
                            {--postcode=10001 : Destination postcode}
                            {--country=US : Destination country}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Smoke-test UPS credentials and print sample rates';

    /**
     * Execute the console command.
     */
    public function handle(RateService $rateService, ConfigResolver $configResolver): int
    {
        $this->info('Mode: '.($this->mode($configResolver) === 'production' ? 'PRODUCTION' : 'SANDBOX'));

        try {
            $rates = $rateService->quote(
                packages: [new CartPackage(weight: 5.0)],
                shipToCountry: $this->option('country'),
                shipToPostcode: $this->option('postcode'),
            );
        } catch (Throwable $e) {
            $this->error('UPS request failed: ['.get_class($e).'] '.$e->getMessage());

            return self::FAILURE;
        }

        if (empty($rates)) {
            $this->warn('Request succeeded but no services were returned. Check the Allowed Services setting.');

            return self::SUCCESS;
        }

        $this->table(
            ['Service', 'Code', 'Amount'],
            array_map(fn ($rate) => [
                $rate->serviceLabel,
                $rate->serviceCode,
                $rate->currency.' '.number_format($rate->pricedAmount(), 2),
            ], $rates)
        );

        return self::SUCCESS;
    }

    /**
     * Resolve the configured mode through the package resolver.
     */
    protected function mode(ConfigResolver $configResolver): string
    {
        return (string) $configResolver->get('mode', 'sandbox');
    }
}
