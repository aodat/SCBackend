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
    Route::post('clients/auth/login', [UserController::class, 'login']);
    Route::post('clients/auth/register',[UserController::class, 'register']);
    Route::get('verify/email/{token}',[UserController::class, 'register']);

    Route::group(['middleware' => ['auth:api']], function () {
        Route::post('clients/logout',[UserController::class, 'logout']);
    });
    // Route::middleware('auth:api')->group( function () {
    // Route::resource('products', ProductController::class);

    Route::get('/unauthenticated',[Controller::class, 'unauthenticated'])->name('unauthenticated');

});



