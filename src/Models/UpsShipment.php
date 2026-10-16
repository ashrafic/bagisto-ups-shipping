<?php

namespace Ashrafic\UpsShipping\Models;

use Illuminate\Database\Eloquent\Model;

class UpsShipment extends Model
{
    /**
     * Fillable property of the model.
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'shipment_id',
        'tracking_number',
        'service_code',
        'status',
        'status_code',
        'estimated_delivery',
        'label_path',
        'label_cost',
        'label_currency',
        'voided_at',
        'tracked_at',
        'raw',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'label_cost' => 'decimal:4',
        'voided_at' => 'datetime',
        'tracked_at' => 'datetime',
        'raw' => 'array',
    ];
}
