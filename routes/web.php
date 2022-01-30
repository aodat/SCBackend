<?php

use App\Http\Controllers\API\Merchant\ShipmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('shipment/{id}/payment', [ShipmentController::class, 'strip']);
Route::post('stripe', [ShipmentController::class, 'stripePost'])->name('stripe.post');