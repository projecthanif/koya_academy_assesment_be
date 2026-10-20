<?php

use App\Http\Controllers\Api\SlotController;
use App\Http\Middleware\ApiKeyValidationMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(ApiKeyValidationMiddleware::class)->group(function () {
    Route::get('slots', [SlotController::class, 'index']);
});
