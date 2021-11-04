<?php

use App\Http\Controllers\Controller;

// Auth
use App\Http\Controllers\API\AuthController;

// Team
use App\Http\Controllers\API\TeamController;

// Merchant
use App\Http\Controllers\API\Merchant\MerchantController;
use App\Http\Controllers\API\Merchant\AddressesController;
use App\Http\Controllers\API\Merchant\DocumentsController;
use App\Http\Controllers\API\Merchant\PaymentMethodsController;
use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Http\Controllers\API\Merchant\TransactionsController;
use App\Http\Controllers\API\Merchant\PickupsController;
use App\Http\Controllers\API\Merchant\InvoiceController;

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
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register',[AuthController::class, 'register']);
    Route::post('auth/forget-password',[AuthController::class, 'forgetPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::get('email/verify', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    Route::get('email/resend', [AuthController::class, 'resend'])->name('verification.resend');
    Route::group(['middleware' => ['auth:api']], function () {
        // MerchantController
        Route::group(['prefix' => 'merchant/'], function () {
            // Dashboard Information 
            Route::get('dashboard',[MerchantController::class,'dashboardInfo']);

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

            // Shipments
            Route::post('shipments/filters',[ShipmentController::class,'index']);
            Route::get('shipments/{id}',[ShipmentController::class,'show']);
            Route::post('shipments/domestic/create',[ShipmentController::class,'createDomesticShipment']);
            Route::post('shipments/express/create',[ShipmentController::class,'createExpressShipment']);
            Route::get('shipments/export/{type}',[ShipmentController::class,'export']);
            Route::post('shipments/print',[ShipmentController::class,'printLabel']);

            // Transactions
            Route::post('transactions',[TransactionsController::class,'index']);
            Route::get('transactions/{id}',[TransactionsController::class,'show']);
            Route::put('transactions/withdraw',[TransactionsController::class,'withDraw']);
            Route::get('transactions/export/{type}',[TransactionsController::class,'export']);

            // Pickups
            Route::post('pickups',[PickupsController::class,'index']);
            Route::post('pickups/create',[PickupsController::class,'store']);
            Route::post('pickup/cancel',[PickupsController::class,'cancel']);

            // Invoice
            Route::get('invoice/finalize/{invoice_id}',[InvoiceController::class,'finalize']);
            Route::post('invoice/create',[InvoiceController::class,'store']);
            Route::delete('invoice/{invoice_id}',[InvoiceController::class,'delete']);
        });
        Route::group(['prefix' => 'team/'], function () {
            Route::post('member/invite', [TeamController::class, 'inviteMember']);

        });
        Route::post('auth/logout',[AuthController::class, 'logout']);
    });
    Route::get('process/shipments',[ShipmentController::class, 'shipmentProcessSQS']);
    Route::get('process/stripe',[InvoiceController::class, 'stripeProcessSQS']);
    
    Route::get('unauthenticated',[Controller::class, 'unauthenticated'])->name('unauthenticated');
});