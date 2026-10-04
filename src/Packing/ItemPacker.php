<?php

namespace Ashrafic\UpsShipping\Packing;

use Ashrafic\UpsShipping\Data\CartPackage;
use Ashrafic\UpsShipping\Support\ConfigResolver;

class ItemPacker implements PackerInterface
{
    /**
     * Pounds per kilogram.
     */
    public const LBS_PER_KG = 2.2046226218;

    /**
     * Default max package weight when unset.
     */
    public const DEFAULT_MAX_WEIGHT = 70.0;

    /**
     * Weight units the packer can convert between.
     *
     * @var array
     */
    public const WEIGHT_UNITS = ['LBS', 'KGS'];

    /**
     * Create a new packer instance.
     */
    public function __construct(protected ConfigResolver $configResolver = new ConfigResolver) {}

    /**
     * Pack items into weight-capped packages.
     */
    public function pack(array $items): array
    {
        $packages = [];

        $maxWeight = (float) ($this->configResolver->get('max_package_weight') ?: self::DEFAULT_MAX_WEIGHT);
        $packagingType = $this->configResolver->get('packaging_type');
        $targetUnit = strtoupper((string) $this->configResolver->get('weight_unit', 'LBS'));

        foreach ($items as $item) {
            $unitWeight = max(
                $this->toUpsUnit((float) ($item['weight'] ?? 0), $item['store_weight_unit'] ?? 'LBS', $targetUnit),
                0.01
            );

            $remaining = $unitWeight * (int) $item['quantity'];

            while ($remaining > 0) {
                $slice = min($remaining, $maxWeight);
                $count = max((int) floor(($slice + 1e-9) / $unitWeight), 1);
                $slice = min($count * $unitWeight, $remaining);

                $packages[] = new CartPackage(
                    weight: round($slice, 2),
                    quantity: $count,
                    packagingType: $packagingType,
                    length: $item['length'] ?? null,
                    width: $item['width'] ?? null,
                    height: $item['height'] ?? null,
                );

                $remaining = round($remaining - $slice, 6);
            }
        }

        return $packages;
    }

    /**
     * Convert a weight from the store unit to the configured UPS unit.
     */
    protected function toUpsUnit(float $weight, string $storeUnit, string $targetUnit): float
    {
        $targetUnit = strtoupper($targetUnit);
        $storeUnit = strtoupper($storeUnit);

        if (! in_array($targetUnit, self::WEIGHT_UNITS, true)) {
            throw new \InvalidArgumentException("Unsupported weight unit [{$targetUnit}].");
        }

        if (! in_array($storeUnit, self::WEIGHT_UNITS, true)) {
            throw new \InvalidArgumentException("Unsupported weight unit [{$storeUnit}].");
        }

        if ($storeUnit === $targetUnit) {
            return $weight;
        }

        return $targetUnit === 'LBS'
            ? $weight * self::LBS_PER_KG
            : $weight / self::LBS_PER_KG;
    }
}
