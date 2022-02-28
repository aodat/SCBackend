<?php

use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\Admin\AddressesController;
use App\Http\Controllers\API\Admin\CarriersController;
use App\Http\Controllers\API\Admin\DocumentsController;
use App\Http\Controllers\API\Admin\DomesticRatesController;
use App\Http\Controllers\API\Admin\ExpressRatesController;
use App\Http\Controllers\API\Admin\MerchantsController;
use App\Http\Controllers\API\Admin\PaymentMethodsController;
use App\Http\Controllers\API\Admin\ShipmentController;
use App\Http\Controllers\API\Admin\TransactionsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "admin" middleware group. Now create something great!
|
 */

Route::group(['middleware' => ['json.response']], function () {
    Route::group(['middleware' => ['auth:api', 'scope:super_admin']], function () {
        Route::post('dashboard', [DashboardController::class, 'index']);
        
        Route::group(['prefix' => 'merchant/'], function () {    
            Route::get('list', [MerchantsController::class, 'index']);

            Route::get('{merchant_id}/info', [MerchantsController::class, 'show']);
            Route::put('update', [MerchantsController::class, 'update']);

            Route::get('{merchant_id}/domestic_rates', [DomesticRatesController::class, 'index']);
            Route::put('{merchant_id}/domestic_rates', [DomesticRatesController::class, 'update']);

            Route::get('{merchant_id}/express_rates', [ExpressRatesController::class, 'index']);
            Route::put('{merchant_id}/express_rates', [ExpressRatesController::class, 'update']);

            Route::get('{merchant_id}/payment_methods', [PaymentMethodsController::class, 'index']);
            Route::put('{merchant_id}/payment_methods', [PaymentMethodsController::class, 'update']);

            Route::get('{merchant_id}/addresses', [AddressesController::class, 'index']);
            Route::put('{merchant_id}/addresses', [AddressesController::class, 'update']);

            Route::get('{merchant_id}/document', [DocumentsController::class, 'index']);
            Route::post('{merchant_id}/document', [DocumentsController::class, 'store']);
            Route::put('{merchant_id}/document/{id}', [DocumentsController::class, 'status']);
        });

        Route::group(['prefix' => 'carriers/'], function () {
            Route::get('list', [CarriersController::class, 'index']);
            Route::get('{carrier_id}/info', [CarriersController::class, 'show']);

            Route::post('create', [CarriersController::class, 'store']);
            Route::put('{carrier_id}/update', [CarriersController::class, 'update']);
        });

        // Shipments
        Route::get('{merchant_id}/shipments/{shipment_id}', [ShipmentController::class, 'show'])
            ->where('merchant_id', '[0-9]+')
            ->where('shipment_id', '[0-9]+');
        Route::post('shipments/{merchant_id}/filters', [ShipmentController::class, 'index'])->where('merchant_id', '[0-9]+');
        Route::post('shipments/track', [ShipmentController::class, 'tracking']);
        Route::put('{merchant_id}/shipments/{shipment_id}', [ShipmentController::class, 'update'])
            ->where('merchant_id', '[0-9]+')
            ->where('shipment_id', '[0-9]+');

        Route::group(['prefix' => 'transactions/'], function () {
            Route::get('list', [TransactionsController::class, 'index']);
            Route::get('export', [TransactionsController::class, 'export']);
            Route::put('withdraw', [TransactionsController::class, 'withdraw']);
        });
    });
});
