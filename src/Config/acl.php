<?php

return [
    [
        'key' => 'sales.ups-shipping',
        'name' => 'ups-shipping::app.admin.menu.ups-shipments',
        'route' => [
            'admin.ups-shipping.index',
            'admin.ups-shipping.labels.create',
            'admin.ups-shipping.labels.store',
            'admin.ups-shipping.labels.download',
            'admin.ups-shipping.labels.void',
            'admin.ups-shipping.labels.track',
        ],
        'sort' => 7,
    ],
];
