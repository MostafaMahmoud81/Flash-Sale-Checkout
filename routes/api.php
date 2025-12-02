<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HoldController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/products/{id}', [ProductController::class, 'getProduct']);
Route::post('/holds', [HoldController::class, 'createHold']);
Route::post('/orders', [OrderController::class, 'createOrder']);
Route::post('/webhook/payment', [WebhookController::class, 'handleWebhook']);
