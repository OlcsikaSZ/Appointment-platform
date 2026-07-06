<?php

use App\Http\Controllers\Api\AdminBookingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicBookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/businesses/{business:slug}', [PublicBookingController::class, 'business']);
    Route::get('/businesses/{business:slug}/services', [PublicBookingController::class, 'services']);
    Route::get('/businesses/{business:slug}/slots', [PublicBookingController::class, 'slots']);
    Route::post('/businesses/{business:slug}/bookings', [PublicBookingController::class, 'store']);

    Route::get('/bookings/{booking:manage_token}', [PublicBookingController::class, 'show']);
    Route::post('/bookings/{booking:manage_token}/cancel', [PublicBookingController::class, 'cancel']);
    Route::post('/bookings/{booking:manage_token}/reschedule', [PublicBookingController::class, 'reschedule']);

    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::middleware('auth:sanctum')->prefix('admin')->group(function (): void {
        Route::get('/businesses/{business}/bookings', [AdminBookingController::class, 'index']);
        Route::get('/businesses/{business}/summary', [AdminBookingController::class, 'summary']);
        Route::get('/businesses/{business}/blocked-times', [AdminBookingController::class, 'blockedTimes']);
        Route::post('/businesses/{business}/blocked-times', [AdminBookingController::class, 'block']);
        Route::delete('/blocked-times/{blockedTime}', [AdminBookingController::class, 'destroyBlock']);
        Route::patch('/bookings/{booking}/status', [AdminBookingController::class, 'updateStatus']);
    });
});
