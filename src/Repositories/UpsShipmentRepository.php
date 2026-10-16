<?php

namespace Ashrafic\UpsShipping\Repositories;

use Ashrafic\UpsShipping\Models\UpsShipment;

class UpsShipmentRepository
{
    /**
     * Create a shipment record.
     */
    public function create(array $attributes): UpsShipment
    {
        return UpsShipment::create($attributes);
    }

    /**
     * Update a shipment record.
     */
    public function update(UpsShipment $shipment, array $attributes): UpsShipment
    {
        $shipment->fill($attributes)->save();

        return $shipment->refresh();
    }

    /**
     * Find a shipment record by id.
     */
    public function find(int $id): ?UpsShipment
    {
        return UpsShipment::find($id);
    }

    /**
     * Find a shipment record by id or throw.
     */
    public function findOrFail(int $id): UpsShipment
    {
        return UpsShipment::findOrFail($id);
    }

    /**
     * Get paginated shipment records, newest first.
     */
    public function paginate(int $perPage = 20)
    {
        return UpsShipment::query()
            ->latest('id')
            ->select(['id', 'order_id', 'tracking_number', 'service_code', 'status', 'estimated_delivery', 'label_path', 'label_cost', 'label_currency', 'voided_at', 'tracked_at', 'created_at'])
            ->paginate($perPage);
    }

    /**
     * Get all shipment records, newest first.
     */
    public function all()
    {
        return UpsShipment::query()->latest('id')->get();
    }
}
