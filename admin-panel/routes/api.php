<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth
    Route::post('auth/telegram', [AuthController::class, 'telegramLogin']);
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Public catalog
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{slug}', [ProductController::class, 'show']);

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        // Profile
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);

        // Cart
        Route::get('cart', [CartController::class, 'index']);
        Route::post('cart', [CartController::class, 'add']);
        Route::put('cart/{cartItem}', [CartController::class, 'update']);
        Route::delete('cart/{cartItem}', [CartController::class, 'remove']);
        Route::delete('cart', [CartController::class, 'clear']);

        // Orders
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);

        // Payments
        Route::post('create-invoice', [OrderController::class, 'createInvoice']);
        Route::post('payment-success', [OrderController::class, 'paymentSuccess']);
        Route::post('refund', [OrderController::class, 'refund']);

        // Telegram Webhook
        Route::post('webhook', [OrderController::class, 'webhook']);
    });
});
