<?php

it('registers the ups carrier in carriers config', function () {
    expect(config('carriers.ups.class'))->toBe('Ashrafic\UpsShipping\Carriers\Ups');
});

it('registers the ups system config group in core config', function () {
    $group = collect(config('core'))->firstWhere('key', 'sales.carriers.ups');

    expect($group)->not->toBeNull()
        ->and($group['fields'])->not->toBeEmpty();
});
