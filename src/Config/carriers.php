<?php

return [
    'ups' => [
        'code' => 'ups',
        'title' => 'UPS Shipping',
        'description' => 'UPS Shipping',
        'active' => false,
        'debug' => false,
        'max_package_weight' => 70,
        'class' => 'Ashrafic\UpsShipping\Carriers\Ups',
    ],
];
