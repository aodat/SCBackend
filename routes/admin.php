<?php


use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Admin\MerchantsController;
use App\Http\Controllers\API\Admin\CarriersController;
use App\Http\Controllers\API\Admin\DomesticRatesController;

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
            Route::get('lists', [MerchantsController::class, 'index']);

            Route::get('{merchant_id}/info', [MerchantsController::class, 'show']);
            Route::put('update', [MerchantsController::class, 'update']);

            // type
            // documents
            // addresses
            // payment_methods
            // express_rates

            Route::get('{merchant_id}/domestic_rates',[DomesticRatesController::class, 'index']);
            Route::post('{merchant_id}/domestic_rates',[DomesticRatesController::class, 'storeOrUpdate']);

        });

        Route::group(['prefix' => 'carriers/'], function () {
            Route::get('lists', [CarriersController::class, 'index']);
            Route::get('{carrier_id}/info', [CarriersController::class, 'show']);
            
            Route::post('create', [CarriersController::class, 'store']);
            Route::put('{carrier_id}/update', [CarriersController::class, 'update']);
        });
    });
    Route::get('unauthenticated', [Controller::class, 'unauthenticated'])->name('unauthenticated');
});
