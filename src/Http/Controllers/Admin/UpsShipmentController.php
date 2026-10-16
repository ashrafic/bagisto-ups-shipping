<?php

namespace Ashrafic\UpsShipping\Http\Controllers\Admin;

use Ashrafic\UpsShipping\Packing\ItemPacker;
use Ashrafic\UpsShipping\Repositories\UpsShipmentRepository;
use Ashrafic\UpsShipping\Services\LabelService;
use Ashrafic\UpsShipping\Services\RateService;
use Ashrafic\UpsShipping\Services\TrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Sales\Models\Order;

class UpsShipmentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected UpsShipmentRepository $repository,
        protected LabelService $labelService,
        protected TrackingService $trackingService,
    ) {}

    /**
     * List UPS shipments and labels.
     */
    public function index(): View
    {
        return view('ups-shipping::admin.shipments.index', [
            'shipments' => $this->repository->paginate(),
        ]);
    }

    /**
     * Show the label creation form for an order.
     */
    public function create(int $orderId): View|RedirectResponse
    {
        $order = Order::findOrFail($orderId);

        if (! $order->shipping_address) {
            session()->flash('error', trans('ups-shipping::app.admin.orders.no-shipping-address'));

            return redirect()->route('admin.sales.orders.view', $orderId);
        }

        $enabled = array_filter(array_map('trim', explode(',', (string) core()->getConfigData('sales.carriers.ups.services'))));

        $services = array_intersect_key(RateService::SERVICES, array_flip($enabled ?: array_keys(RateService::SERVICES)));

        return view('ups-shipping::admin.orders.label-create', [
            'order' => $order,
            'services' => $services,
        ]);
    }

    /**
     * Create a shipping label for an order.
     */
    public function store(int $orderId): RedirectResponse
    {
        $data = request()->validate([
            'service_code' => 'required|string',
        ]);

        $order = Order::findOrFail($orderId);

        if (! in_array($data['service_code'], array_keys(RateService::SERVICES), true)) {
            session()->flash('error', trans('ups-shipping::app.admin.orders.label-failed').': unknown service.');

            return redirect()->back();
        }

        try {
            $shipment = $this->labelService->create(
                shipper: $this->origin(),
                recipient: $this->recipient($order),
                packages: $this->packages($order),
                serviceCode: $data['service_code'],
            );
        } catch (\Throwable $e) {
            logger()->error('[UPS] label creation failed', ['exception' => $e, 'order_id' => $orderId]);

            session()->flash('error', trans('ups-shipping::app.admin.orders.label-failed').': '.$e->getMessage());

            return redirect()->back();
        }

        $this->repository->update($shipment, ['order_id' => $orderId]);

        $order->shipments()->latest('id')->first()?->update(['track_number' => $shipment->tracking_number]);

        session()->flash('success', trans('ups-shipping::app.admin.orders.label-created').' — '.$shipment->tracking_number);

        return redirect()->route('admin.sales.orders.view', $orderId);
    }

    /**
     * Download a label PDF.
     */
    public function download(int $id): StreamedResponse
    {
        $shipment = $this->repository->findOrFail($id);

        return Storage::disk(config('carriers.ups.label_disk', 'private'))->download($shipment->label_path, 'ups-label-'.$shipment->tracking_number.'.pdf');
    }

    /**
     * Void a label.
     */
    public function void(int $id): RedirectResponse
    {
        $shipment = $this->repository->findOrFail($id);

        try {
            $this->labelService->void($shipment);

            session()->flash('success', trans('ups-shipping::app.admin.shipments.voided').' — '.$shipment->tracking_number);
        } catch (\Throwable $e) {
            logger()->error('[UPS] label void failed', ['exception' => $e]);

            session()->flash('error', trans('ups-shipping::app.admin.shipments.void-failed').': '.$e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Refresh tracking details for a shipment.
     */
    public function track(int $id): RedirectResponse
    {
        $shipment = $this->repository->findOrFail($id);

        try {
            $result = $this->trackingService->track($shipment->tracking_number);

            $this->repository->update($shipment, [
                'status' => $result->status,
                'status_code' => $result->statusCode,
                'estimated_delivery' => $result->estimatedDelivery,
                'tracked_at' => now(),
                'raw' => array_merge($shipment->raw ?? [], ['tracking' => $result->events]),
            ]);

            session()->flash('success', trans('ups-shipping::app.admin.shipments.tracked').' — '.$result->status);
        } catch (\Throwable $e) {
            logger()->error('[UPS] tracking refresh failed', ['exception' => $e]);

            session()->flash('error', trans('ups-shipping::app.admin.shipments.track-failed').': '.$e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Shipper origin from the channel shipping configuration.
     */
    protected function origin(): array
    {
        return [
            'name' => core()->getConfigData('sales.shipping.origin.store_name') ?: 'Store',
            'phone' => core()->getConfigData('sales.shipping.origin.contact'),
            'address' => core()->getConfigData('sales.shipping.origin.address'),
            'city' => core()->getConfigData('sales.shipping.origin.city'),
            'state' => core()->getConfigData('sales.shipping.origin.state'),
            'zipcode' => core()->getConfigData('sales.shipping.origin.zipcode'),
            'country' => core()->getConfigData('sales.shipping.origin.country'),
        ];
    }

    /**
     * Recipient block from the order shipping address.
     */
    protected function recipient(Order $order): array
    {
        $address = $order->shipping_address;

        return [
            'name' => $address->name,
            'phone' => $address->phone,
            'address' => $address->address ?? $address->address1,
            'city' => $address->city,
            'state' => $address->state,
            'zipcode' => $address->postcode,
            'country' => $address->country,
        ];
    }

    /**
     * Packed packages from the order items.
     */
    protected function packages(Order $order): array
    {
        $items = $order->items
            ->filter(fn ($item) => (float) $item->weight > 0)
            ->map(fn ($item) => [
                'weight' => (float) $item->weight,
                'quantity' => (int) $item->qty_ordered,
                'store_weight_unit' => core()->getConfigData('general.general.locale_options.weight_unit') ?: 'LBS',
                'length' => $item->product->length ?? null,
                'width' => $item->product->width ?? null,
                'height' => $item->product->height ?? null,
            ])
            ->values()
            ->all();

        return app(ItemPacker::class)->pack($items);
    }
}
