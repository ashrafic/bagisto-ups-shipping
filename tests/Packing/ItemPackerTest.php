<?php

use Ashrafic\UpsShipping\Packing\ItemPacker;

it('creates one package per item group when under max weight', function () {
    config()->set('carriers.ups.packaging_type', '02');

    $packages = (new ItemPacker)->pack([
        ['weight' => 2.0, 'quantity' => 3],
    ]);

    expect($packages)->toHaveCount(1)
        ->and($packages[0]->weight)->toBe(6.0)
        ->and($packages[0]->quantity)->toBe(3);
});

it('splits heavy items into multiple packages by max weight', function () {
    config()->set('carriers.ups.packaging_type', '02');
    config()->set('carriers.ups.max_package_weight', 10);

    $packages = (new ItemPacker)->pack([
        ['weight' => 3.0, 'quantity' => 7],
    ]);

    expect($packages)->toHaveCount(3)
        ->and($packages[0]->weight)->toBe(9.0)
        ->and($packages[1]->weight)->toBe(9.0)
        ->and($packages[2]->weight)->toBe(3.0);
});

it('converts store weight unit to the configured ups unit', function () {
    config()->set('carriers.ups.weight_unit', 'LBS');

    $packages = (new ItemPacker)->pack([
        ['weight' => 1.0, 'quantity' => 1, 'store_weight_unit' => 'KGS'],
    ]);

    expect(round($packages[0]->weight, 2))->toBe(2.2);
});

it('ships an item heavier than the cap as one package', function () {
    config()->set('carriers.ups.max_package_weight', 10);

    $packages = (new ItemPacker)->pack([
        ['weight' => 15.0, 'quantity' => 1],
    ]);

    expect($packages)->toHaveCount(1)
        ->and($packages[0]->weight)->toBe(15.0)
        ->and($packages[0]->quantity)->toBe(1);
});

it('rejects unsupported weight units loudly', function () {
    config()->set('carriers.ups.weight_unit', 'OUNCES');

    expect(fn () => (new ItemPacker)->pack([
        ['weight' => 1.0, 'quantity' => 1],
    ]))->toThrow(InvalidArgumentException::class, 'Unsupported weight unit [OUNCES].');
});
