<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Middleware\ApiKeyValidationMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(ApiKeyValidationMiddleware::class)->group(function () {
    Route::get('slots', [SlotController::class, 'index']);
    Route::get('bookings/{id}', [BookingController::class, 'show']);
    Route::get('bookings', [BookingController::class, 'index']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::delete('bookings/{id}', [BookingController::class, 'destroy']);
});
