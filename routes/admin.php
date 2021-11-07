<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Controller;

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
    Route::group(['middleware' => ['auth:api','scope:super_admin']], function () {
        // Route::
    });
    Route::get('unauthenticated', [Controller::class, 'unauthenticated'])->name('unauthenticated');
});