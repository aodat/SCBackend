<?php


use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Admin\MerchantsController;
use App\Http\Controllers\API\Admin\CarriersController;
use App\Http\Controllers\API\Admin\DomesticRatesController;
use App\Http\Controllers\API\Admin\ExpressRatesController;
use App\Http\Controllers\API\Admin\PaymentMethodsController;
use App\Http\Controllers\API\Admin\AddressesController;
use App\Http\Controllers\API\Admin\DocumentController;
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
        Route::group(['prefix' => 'merchant/'], function () {
            Route::get('list', [MerchantsController::class, 'index']);

            Route::get('{merchant_id}/info', [MerchantsController::class, 'show']);
            Route::put('update', [MerchantsController::class, 'update']);
            
            Route::get('{merchant_id}/domestic_rates',[DomesticRatesController::class, 'index']);
            Route::put('{merchant_id}/domestic_rates',[DomesticRatesController::class, 'update']);

            Route::get('{merchant_id}/express_rates',[ExpressRatesController::class, 'index']);
            Route::put('{merchant_id}/express_rates',[ExpressRatesController::class, 'update']);

            Route::get('{merchant_id}/payment_methods',[PaymentMethodsController::class, 'index']);
            Route::put('{merchant_id}/payment_methods',[PaymentMethodsController::class, 'update']);

            Route::get('{merchant_id}/addresses', [AddressesController::class, 'index']);
            Route::put('{merchant_id}/addresses', [AddressesController::class, 'update']);

            Route::get('{merchant_id}/document', [DocumentController::class, 'index']);
            Route::post('{merchant_id}/document', [DocumentController::class, 'update']);


        });

        Route::group(['prefix' => 'carriers/'], function () {
            Route::get('list', [CarriersController::class, 'index']);
            Route::get('{carrier_id}/info', [CarriersController::class, 'show']);
            
            Route::post('create', [CarriersController::class, 'store']);
            Route::put('{carrier_id}/update', [CarriersController::class, 'update']);
        });
    });
    Route::get('unauthenticated', [Controller::class, 'unauthenticated'])->name('unauthenticated');
});
