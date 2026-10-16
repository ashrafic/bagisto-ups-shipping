<?php

namespace Ashrafic\UpsShipping\Console\Commands;

use Ashrafic\UpsShipping\Services\AddressValidationService;
use Illuminate\Console\Command;
use Throwable;

class ValidateAddressCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ups:validate-address
                            {--street= : Street address line}
                            {--city= : City}
                            {--state= : State or province code}
                            {--zip= : Postcode}
                            {--country=US : Country code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate an address against the UPS address database';

    /**
     * Execute the console command.
     */
    public function handle(AddressValidationService $service): int
    {
        try {
            $result = $service->validate([
                'address' => array_filter([(string) $this->option('street')]),
                'city' => $this->option('city'),
                'state' => $this->option('state'),
                'zipcode' => $this->option('zip'),
                'country' => $this->option('country'),
            ]);
        } catch (Throwable $e) {
            $this->error('UPS request failed: ['.get_class($e).'] '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Classification: '.($result['classification'] ?? 'unknown'));

        $resolved = $result['resolved'];

        if (! empty($resolved)) {
            $this->table(
                ['Field', 'Resolved value'],
                collect($resolved)->map(fn ($value, $key) => [$key, is_array($value) ? implode(', ', $value) : (string) $value])->values()->all()
            );
        }

        foreach ($result['messages'] as $message) {
            $this->line($message);
        }

        return $result['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
