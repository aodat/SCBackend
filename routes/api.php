<?php

use App\Http\Controllers\Controller;

// Auth
use App\Http\Controllers\API\AuthController;

// Merchant
use App\Http\Controllers\API\Merchant\MerchantController;
use App\Http\Controllers\API\Merchant\AddressesController;
use App\Http\Controllers\API\Merchant\DocumentsController;
use App\Http\Controllers\API\Merchant\PaymentMethodsController;
use App\Http\Controllers\API\Merchant\SendersController;
use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Http\Controllers\API\Merchant\TransactionsController;
use App\Http\Controllers\API\Merchant\PickupsController;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['json.response']], function () { 
    Route::post('clients/auth/login', [AuthController::class, 'login']);
    Route::post('clients/auth/register',[AuthController::class, 'register']);
    Route::post('clients/auth/forgetpassword',[AuthController::class, 'forgetPassword']);
    Route::post('clients/password/reset', [AuthController::class, 'sendResetResponse']);

    Route::get('email/verify/{id}', [AuthController::class, 'verify'])->name('verification.verify');
    Route::get('email/resend', [AuthController::class, 'resend'])->name('verification.resend');
        
    Route::group(['middleware' => ['auth:api']], function () {
        // MerchantController
        Route::group(['prefix' => 'merchant/'], function () {
            Route::post('verify/phone',[MerchantController::class,'verifyPhoneNumber']);
            // Merchant Profile
            Route::get('profile',[MerchantController::class,'profile']);
            Route::put('profile/update-profile',[MerchantController::class,'updateProfile']);
            Route::put('profile/update-password',[MerchantController::class,'updatePassword']);
        
            // Payment-methods
            Route::get('payment-methods',[PaymentMethodsController::class,'index']);
            Route::post('payment-methods/create',[PaymentMethodsController::class,'createPaymentMethods']);
            Route::delete('payment-methods/{id}',[PaymentMethodsController::class,'deletePaymentMethods']);

            // Documents
            Route::get('documents',[DocumentsController::class,'index']);
            Route::post('documents/create',[DocumentsController::class,'createDocuments']);
            Route::delete('documents/{id}',[DocumentsController::class,'deleteDocuments']);

            // Addresses // Done
            Route::get('addresses',[AddressesController::class,'index']);
            Route::post('addresses/create',[AddressesController::class,'createAddresses']);
            Route::delete('addresses/{id}',[AddressesController::class,'deleteAddresses']);

            // Senders
            Route::get('senders',[SendersController::class,'index']);
            Route::post('senders/create',[SendersController::class,'createSenders']);
            Route::delete('senders/{id}',[SendersController::class,'deleteSenders']);

            // Shipments
            Route::post('shipments',[ShipmentController::class,'index']);
            Route::get('shipments/{id}',[ShipmentController::class,'show']);
            Route::post('shipments/create',[ShipmentController::class,'create']);
            Route::get('shipments/export/{type}',[ShipmentController::class,'export']);

            // Transactions
            Route::post('transactions',[TransactionsController::class,'index']);
            Route::get('transactions/{id}',[TransactionsController::class,'show']);
            Route::put('transactions/withdraw',[TransactionsController::class,'withDraw']);
            Route::get('transactions/export/{type}',[TransactionsController::class,'export']);

            // Pickups
            Route::post('pickups',[PickupsController::class,'index']);
            Route::post('pickups/create',[PickupsController::class,'createPickup']);
        });

        Route::post('clients/logout',[AuthController::class, 'logout']);
    });
    Route::get('/unauthenticated',[Controller::class, 'unauthenticated'])->name('unauthenticated');
});