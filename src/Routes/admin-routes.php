<?php

use Ashrafic\UpsShipping\Http\Controllers\Admin\UpsShipmentController;
use Illuminate\Support\Facades\Route;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;

Route::group(['middleware' => ['web', 'admin', NoCacheMiddleware::class], 'prefix' => config('app.admin_url').'/ups-shipping'], function () {
    Route::controller(UpsShipmentController::class)->group(function () {
        Route::get('', 'index')->name('admin.ups-shipping.index');

        Route::get('orders/{orderId}/label/create', 'create')->name('admin.ups-shipping.labels.create');

        Route::post('orders/{orderId}/label', 'store')->name('admin.ups-shipping.labels.store');

        Route::get('labels/{id}/download', 'download')->name('admin.ups-shipping.labels.download');

        Route::post('labels/{id}/void', 'void')->name('admin.ups-shipping.labels.void');

        Route::post('labels/{id}/track', 'track')->name('admin.ups-shipping.labels.track');
    });
});
