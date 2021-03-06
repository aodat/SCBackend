<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Merchant\AddressesController;
use App\Http\Controllers\API\Merchant\CarrierController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\Merchant\DocumentsController;
use App\Http\Controllers\API\Merchant\PaymentLinksController;
use App\Http\Controllers\API\Merchant\MerchantController;
use App\Http\Controllers\API\Merchant\PaymentMethodsController;
use App\Http\Controllers\API\Merchant\PickupsController;
use App\Http\Controllers\API\Merchant\RulesController;
use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Http\Controllers\API\Merchant\TransactionsController;
use App\Http\Controllers\API\TeamController;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use Libs\Aramex;

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

Route::group(['middleware' => ['json.response', 'db.row']], function () {
    Route::middleware(['throttle:ip_address'])->group(function () {
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/forget-password', [AuthController::class, 'forgetPassword']);
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::get('email/verify', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    Route::get('email/resend', [AuthController::class, 'resend'])->name('verification.resend');
    Route::group(['middleware' => ['auth:api']], function () {
        Route::group(['middleware' => ['check.merchant']], function () {
            Route::group(['prefix' => 'merchant/'], function () {
                Route::get('generate-secret', [AuthController::class, 'generateSecretKey']);
                Route::put('change-secret', [AuthController::class, 'changeSecret']);
                Route::delete('revoke-secret', [AuthController::class, 'revokeSecretKey']);

                Route::get('carrier/list', [CarrierController::class, 'index']);
                Route::put('carrier/{carrier_id}/update', [CarrierController::class, 'update']);
                Route::delete('carrier/{carrier_id}/env', [CarrierController::class, 'delete']);

                // Dashboard Information
                Route::post('dashboard', [DashboardController::class, 'index']);
                Route::post('pincode', [MerchantController::class, 'pincode']);

                Route::post('phone/verify', [MerchantController::class, 'verifyPhone']);

                Route::put('update-info', [MerchantController::class, 'updateMerchantProfile']);

                // Merchant Profile
                Route::get('info', [MerchantController::class, 'merchantProfile']);

                // User Information
                Route::get('user/profile', [MerchantController::class, 'profile']);
                Route::put('user/update-profile', [MerchantController::class, 'updateProfile']);
                Route::put('user/update-password', [MerchantController::class, 'updatePassword']);

                // Payment-methods
                Route::group(['middleware' => ['scope:payment,admin']], function () {
                    Route::get('payment-methods', [PaymentMethodsController::class, 'index']);
                    Route::post('payment-methods/create', [PaymentMethodsController::class, 'store']);
                    Route::post('payment-methods/{id}', [PaymentMethodsController::class, 'delete'])->where('id', '[0-9]+');
                });

                // Documents
                Route::get('documents', [DocumentsController::class, 'index']);
                Route::post('documents/create', [DocumentsController::class, 'store']);
                Route::delete('documents/{id}', [DocumentsController::class, 'delete'])->where('id', '[0-9]+');

                // Addresses
                Route::get('addresses', [AddressesController::class, 'index']);
                Route::post('addresses/create', [AddressesController::class, 'store']);
                Route::delete('addresses/{id}', [AddressesController::class, 'delete'])->where('id', '[0-9]+');

                // Shipments
                Route::group(['middleware' => ['scope:shipping,admin']], function () {
                    Route::get('shipments/{id}', [ShipmentController::class, 'show'])->where('id', '[0-9]+');
                    Route::get('shipments/template', [ShipmentController::class, 'template']);
                    Route::delete('shipments/{id}', [ShipmentController::class, 'delete'])->where('id', '[0-9]+');

                    Route::post('shipments/export/{type}', [ShipmentController::class, 'export']);
                    Route::post('shipments/filters', [ShipmentController::class, 'index']);
                    Route::post('shipments/export/{type}', [ShipmentController::class, 'export']);
                    Route::post('shipments/domestic/create', [ShipmentController::class, 'createDomesticShipment']);
                    Route::post('shipments/express/create', [ShipmentController::class, 'createExpressShipment']);
                    Route::post('shipments/print', [ShipmentController::class, 'printLabel']);
                    Route::post('shipments/calculate/fees', [ShipmentController::class, 'calculate']);
                });

                // Transactions
                Route::post('transactions', [TransactionsController::class, 'index']);
                Route::post('transactions/dates', [TransactionsController::class, 'byDates']);
                Route::get('transactions/{id}', [TransactionsController::class, 'show'])->where('id', '[0-9]+');
                Route::post('transactions/export', [TransactionsController::class, 'export']);

                // Pickups
                Route::post('pickups', [PickupsController::class, 'index']);
                Route::get('pickup/{pickup_id}', [PickupsController::class, 'show']);
                Route::post('pickups/create', [PickupsController::class, 'store']);
                Route::post('pickup/cancel', [PickupsController::class, 'cancel']);

                // Invoice
                Route::post('payments_link', [PaymentLinksController::class, 'index']);
                Route::post('payments_link/create', [PaymentLinksController::class, 'store']);
                Route::get('payments_link/{payments_id}', [PaymentLinksController::class, 'show']);
                Route::delete('payments_link/{payments_id}', [PaymentLinksController::class, 'delete'])->where('payments_id', '[0-9]+');

                Route::group(['middleware' => ['scope:admin']], function () {
                    Route::get('rules', [RulesController::class, 'index']);
                    Route::post('rules/create', [RulesController::class, 'store']);
                    Route::put('rules/{rule_id}', [RulesController::class, 'status']);
                    Route::delete('rules/{rule_id}', [RulesController::class, 'delete']);

                    Route::put('transfer', [TransactionsController::class, 'transfer']);
                    Route::put('deposit', [TransactionsController::class, 'deposit']);
                    Route::put('deposit/request', [TransactionsController::class, 'depositwRequest']);


                    
                    Route::put('withdraw', [TransactionsController::class, 'withdraw']);
                    Route::put('transactions/withdraw', [TransactionsController::class, 'withdraw']);
                });

                Route::get('countries', [MerchantController::class, 'getCountries']);
                Route::get('cities/{city_code}', [MerchantController::class, 'getCities']);
                Route::get('areas/{area_code}', [MerchantController::class, 'getAreas']);
            });

            Route::group(['middleware' => ['scope:admin']], function () {
                Route::group(['prefix' => 'team/'], function () {
                    Route::get('list', [TeamController::class, 'index']);
                    Route::put('member', [TeamController::class, 'changeMemberRole']);
                    Route::post('member/invite', [TeamController::class, 'inviteMember']);
                    Route::delete('member/{user_id}', [TeamController::class, 'deleteMember'])->where('user_id', '[0-9]+');
                });
            });
        });

        Route::post('auth/logout', [AuthController::class, 'logout']);
    });

    Route::post('aramex-webhook', [Aramex::class, 'webhook']);
    Route::get('payments_link/{hash}', [PaymentLinksController::class, 'hash']);
    Route::post('payments_link/charge', [PaymentLinksController::class, 'charge']);
});
Route::get('unauthenticated', [Controller::class, 'unauthenticated'])->name('unauthenticated');
