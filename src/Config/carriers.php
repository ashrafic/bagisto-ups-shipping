<?php

return [
    'ups' => [
        'code' => 'ups',
        'title' => 'UPS Shipping',
        'description' => 'UPS Shipping',
        'active' => false,
        'debug' => false,
        'mode' => 'sandbox',
        'services' => '',
        'weight_unit' => 'LBS',
        'packaging_type' => '02',
        'max_package_weight' => 70,
        'handling_fee_type' => 'fixed',
        'handling_fee_amount' => 0,
        'rate_cache_ttl' => 15,
        'class' => 'Ashrafic\UpsShipping\Carriers\Ups',
    ],
];
