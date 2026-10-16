<?php

it('resolves every admin translation key used by the package', function () {
    $keys = [
        'app.admin.menu.ups-shipments',
        'app.admin.shipments.title',
        'app.admin.shipments.tracking-number',
        'app.admin.shipments.service',
        'app.admin.shipments.status',
        'app.admin.shipments.cost',
        'app.admin.shipments.estimated-delivery',
        'app.admin.shipments.actions',
        'app.admin.shipments.download',
        'app.admin.shipments.void',
        'app.admin.shipments.refresh-tracking',
        'app.admin.shipments.empty',
        'app.admin.shipments.voided',
        'app.admin.shipments.void-failed',
        'app.admin.shipments.tracked',
        'app.admin.shipments.track-failed',
        'app.admin.orders.create-label',
        'app.admin.orders.service',
        'app.admin.orders.label-created',
        'app.admin.orders.label-failed',
        'app.admin.orders.no-shipping-address',
        'app.admin.system.ups',
        'app.admin.system.ups-info',
        'app.admin.system.account-number',
        'app.admin.system.account-number-info',
    ];

    foreach ($keys as $key) {
        $translated = trans('ups-shipping::'.$key);

        expect($translated)->not->toBe('ups-shipping::'.$key)
            ->and($translated)->not->toBeEmpty();
    }
});

it('registers the ups menu and acl entries', function () {
    expect(collect(config('menu.admin'))->firstWhere('key', 'sales.ups-shipping'))->not->toBeNull()
        ->and(collect(config('acl'))->firstWhere('key', 'sales.ups-shipping'))->not->toBeNull();
});
