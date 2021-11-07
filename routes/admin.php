<?php


use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Admin\MerchantsController;


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
        });
        Route::group(['prefix' => 'carirese/'], function () {
            // List
            // Show
            // Delete
            // Edit
        });
    });
    Route::get('unauthenticated', [Controller::class, 'unauthenticated'])->name('unauthenticated');
});
