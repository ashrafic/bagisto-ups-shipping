<?php

namespace Ashrafic\UpsShipping\Packing;

use Ashrafic\UpsShipping\Data\CartPackage;

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
     * Pack items into weight-capped packages.
     */
    public function pack(array $items): array
    {
        $packages = [];

        foreach ($items as $item) {
            $unitWeight = max($this->toUpsUnit((float) $item['weight'], $item['store_weight_unit'] ?? 'LBS'), 0.01);
            $maxWeight = (float) (config('carriers.ups.max_package_weight') ?: self::DEFAULT_MAX_WEIGHT);
            $packagingType = config('carriers.ups.packaging_type');

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
    protected function toUpsUnit(float $weight, string $storeUnit): float
    {
        $targetUnit = strtoupper(config('carriers.ups.weight_unit', 'LBS'));
        $storeUnit = strtoupper($storeUnit);

        if ($storeUnit === $targetUnit) {
            return $weight;
        }

        return $targetUnit === 'LBS'
            ? $weight * self::LBS_PER_KG
            : $weight / self::LBS_PER_KG;
    }
}
