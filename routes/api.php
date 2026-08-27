<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImportController;
use App\Http\Controllers\Api\SupplierController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    Route::apiResource('suppliers', SupplierController::class);

    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::get('products/{product}/audit-log', [ProductController::class, 'auditLog']);

    Route::apiResource('imports', ProductImportController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::get('imports/{import}/items', [ProductImportController::class, 'items']);
    Route::post('imports/{import}/confirm', [ProductImportController::class, 'confirm']);
    Route::post('imports/{import}/cancel', [ProductImportController::class, 'cancel']);
});
