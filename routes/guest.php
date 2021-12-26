<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\Merchant\MerchantController;
use App\Http\Controllers\API\Merchant\ShipmentController;

Route::group(['middleware' => ['json.response']], function () {
    Route::get('countries', [MerchantController::class, 'getCountries']);
    Route::get('cities/{city_code}', [MerchantController::class, 'getCities']);
    Route::get('areas/{area_code}', [MerchantController::class, 'getAreas']);

    Route::post('shipments/calculate/fees', [ShipmentController::class, 'calculate']);
});