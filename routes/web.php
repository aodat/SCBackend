<?php

use App\Http\Controllers\API\Merchant\StripController;
use Illuminate\Support\Facades\Redirect;
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
    return Redirect::to('https://beta.shipcash.net');
});

Route::get('shipment/{id}/payment', [StripController::class, 'strip']);
Route::post('stripe', [StripController::class, 'stripePost'])->name('stripe.post');