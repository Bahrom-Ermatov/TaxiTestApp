<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Api\DeskController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\AuthController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//Drivers operation
Route::get('driver/select/{amount}/{latitude}/{longitude}', [DriverController::class, 'select_driver']);
Route::post('driver/charge-commission', [DriverController::class, 'charge_commission']);

Route::post('driver/register', [DriverController::class, 'create_driver'])->middleware('auth:sanctum');


//Authorization
Route::post('auth/register', [AuthController::class, 'createUser']);
Route::post('auth/login', [AuthController::class, 'loginUser']);

