<?php

use App\Http\Controllers\Api\AdminBookingController;
use App\Http\Controllers\Api\AdminServiceController;
use App\Http\Controllers\Api\AdminWebsiteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicBookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/businesses/{business:slug}', [PublicBookingController::class, 'business']);
    Route::get('/businesses/{business:slug}/services', [PublicBookingController::class, 'services']);
    Route::get('/businesses/{business:slug}/slots', [PublicBookingController::class, 'slots']);
    Route::get('/businesses/{business:slug}/availability', [PublicBookingController::class, 'availability']);
    Route::post('/businesses/{business:slug}/bookings', [PublicBookingController::class, 'store']);

   Route::get('/bookings/{booking:manage_token}', [PublicBookingController::class, 'show']);

    Route::get(
        '/bookings/{booking:manage_token}/slots',
        [PublicBookingController::class, 'manageSlots']
    );

    Route::post('/bookings/{booking:manage_token}/cancel', [PublicBookingController::class, 'cancel']);
    Route::post('/bookings/{booking:manage_token}/reschedule', [PublicBookingController::class, 'reschedule']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::middleware('auth:sanctum')->prefix('admin')->group(function (): void {
        Route::get('/businesses/{business}/bookings', [AdminBookingController::class, 'index']);
        Route::get('/businesses/{business}/summary', [AdminBookingController::class, 'summary']);
        Route::get('/businesses/{business}/today', [AdminBookingController::class, 'today']);
        Route::get('/businesses/{business}/calendar', [AdminBookingController::class, 'calendar']);
        Route::get('/businesses/{business}/day', [AdminBookingController::class, 'day']);
        Route::get('/businesses/{business}/slots', [AdminBookingController::class, 'slots']);
        Route::post('/businesses/{business}/bookings', [AdminBookingController::class, 'store']);
        Route::get('/businesses/{business}/services', [AdminServiceController::class, 'index']);
        Route::post('/businesses/{business}/services', [AdminServiceController::class, 'store']);
        Route::patch('/services/{service}', [AdminServiceController::class, 'update']);
        Route::delete('/services/{service}', [AdminServiceController::class, 'destroy']);
        Route::post('/services/{service}/image', [AdminServiceController::class, 'uploadImage']);
        Route::delete('/services/{service}/image', [AdminServiceController::class, 'deleteImage']);
        Route::post('/businesses/{business}/services/reorder', [AdminServiceController::class, 'reorder']);
        Route::get('/businesses/{business}/blocked-times', [AdminBookingController::class, 'blockedTimes']);
        Route::post('/businesses/{business}/blocked-times', [AdminBookingController::class, 'block']);
        Route::delete('/blocked-times/{blockedTime}', [AdminBookingController::class, 'destroyBlock']);
        Route::patch('/bookings/{booking}/status', [AdminBookingController::class, 'updateStatus']);

        Route::get('/businesses/{business}/website', [AdminWebsiteController::class, 'show']);
        Route::patch('/businesses/{business}/website', [AdminWebsiteController::class, 'update']);
        Route::post('/businesses/{business}/logo', [AdminWebsiteController::class, 'uploadLogo']);
        Route::delete('/businesses/{business}/logo', [AdminWebsiteController::class, 'deleteLogo']);
        Route::post('/businesses/{business}/reviews', [AdminWebsiteController::class, 'storeReview']);
        Route::patch('/reviews/{review}', [AdminWebsiteController::class, 'updateReview']);
        Route::delete('/reviews/{review}', [AdminWebsiteController::class, 'destroyReview']);
        Route::post('/businesses/{business}/faqs', [AdminWebsiteController::class, 'storeFaq']);
        Route::patch('/faqs/{faq}', [AdminWebsiteController::class, 'updateFaq']);
        Route::delete('/faqs/{faq}', [AdminWebsiteController::class, 'destroyFaq']);
    });
});
