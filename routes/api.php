<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

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
    Route::post('clients/auth/forgetpassword',[recoveryController::class, 'forgetpassword']);

    Route::get('email/verify/{id}', [recoveryController::class, 'verify'])->name('verification.verify');
    Route::get('email/resend', [recoveryController::class, 'sendResetResponse'])->name('verification.resend');
        
    Route::group(['middleware' => ['auth:api']], function () {
        Route::group(['prefix' => 'user/'], function () {
            Route::get('check/phone',[OtpController::class,'checkOTP']);
            Route::get('send/phone/verification',[OtpController::class,'sendVerification']);
            Route::post('verify/phone',[OtpController::class,'verifyPhoneNumber']);
        });


        // API/MerchantController
        Route::group(['prefix' => 'merchant/'], function () {
            // Merchant Profile
            Route::get('profile',[MerchantController::class,'profile']);
            Route::put('profile/update-profile',[MerchantController::class,'updateProfile']);
            Route::put('profile/update-password',[MerchantController::class,'updatePassword']);

            // Merchant 
            // type {{ payment-methods , documents , addresses , senders }}
            Route::get('{col}',[MerchantController::class,'getMerchantJson']);
            Route::post('{col}/create',[MerchantController::class,'createMerchantJson']);
            Route::delete('{col}/{id}',[MerchantController::class,'deleteMerchantJson']);
        });

        Route::post('clients/logout',[AuthController::class, 'logout']);
        
    });
    Route::get('/unauthenticated',[Controller::class, 'unauthenticated'])->name('unauthenticated');
});



