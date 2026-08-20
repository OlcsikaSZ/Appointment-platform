<?php

use App\Http\Controllers\Api\AdminBookingController;
use App\Http\Controllers\Api\AdminEmailController;
use App\Http\Controllers\Api\AdminServiceController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AdminScheduleController;
use App\Http\Controllers\Api\AdminWebsiteController;
use App\Http\Controllers\Api\AdminCustomerController;
use App\Http\Controllers\Api\AdminReminderController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerAccountController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\PublicReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/businesses/{business:slug}', [PublicBookingController::class, 'business']);
    Route::get('/businesses/{business:slug}/services', [PublicBookingController::class, 'services']);
    Route::get('/businesses/{business:slug}/slots', [PublicBookingController::class, 'slots']);
    Route::get('/businesses/{business:slug}/availability', [PublicBookingController::class, 'availability']);
    Route::get('/businesses/{business:slug}/availability-calendar', [PublicBookingController::class, 'availabilityCalendar']);
    Route::post('/businesses/{business:slug}/bookings', [PublicBookingController::class, 'store']);
    Route::post('/businesses/{business:slug}/reviews', [PublicReviewController::class, 'store'])
        ->middleware('throttle:3,10');
    Route::post('/businesses/{business:slug}/customer-auth/register', [CustomerAuthController::class, 'register'])
        ->middleware('throttle:3,10');
    Route::post('/businesses/{business:slug}/customer-auth/verify-registration', [CustomerAuthController::class, 'verifyRegistration'])
        ->middleware('throttle:10,1');
    Route::post('/businesses/{business:slug}/customer-auth/login', [CustomerAuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('/businesses/{business:slug}/customer-auth/password/forgot', [CustomerAuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,10');
    Route::post('/businesses/{business:slug}/customer-auth/password/reset', [CustomerAuthController::class, 'resetPassword'])
        ->middleware('throttle:10,1');

    Route::get('/bookings/{booking:manage_token}', [PublicBookingController::class, 'show']);
    Route::get('/bookings/{booking:manage_token}/calendar.ics', [PublicBookingController::class, 'calendar']);

    Route::get(
        '/bookings/{booking:manage_token}/slots',
        [PublicBookingController::class, 'manageSlots']
    );
    Route::get(
        '/bookings/{booking:manage_token}/availability',
        [PublicBookingController::class, 'manageAvailability']
    );
    Route::get(
        '/bookings/{booking:manage_token}/availability-calendar',
        [PublicBookingController::class, 'manageAvailabilityCalendar']
    );

    Route::post('/bookings/{booking:manage_token}/cancel', [PublicBookingController::class, 'cancel']);
    Route::post('/bookings/{booking:manage_token}/reschedule', [PublicBookingController::class, 'reschedule']);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/owner/activate', [AuthController::class, 'activateOwner'])->middleware('throttle:10,1');
    Route::post('/auth/owner/resend-verification', [AuthController::class, 'resendOwnerActivation'])->middleware('throttle:3,10');
    Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,10');
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', 'customer.access'])->prefix('customer')->group(function (): void {
        Route::get('/me', [CustomerAccountController::class, 'me']);
        Route::get('/bookings', [CustomerAccountController::class, 'bookings']);
        Route::patch('/profile', [CustomerAccountController::class, 'update']);
        Route::patch('/password', [CustomerAccountController::class, 'updatePassword']);
        Route::get('/sessions', [CustomerAccountController::class, 'sessions']);
        Route::delete('/sessions/{tokenId}', [CustomerAccountController::class, 'destroySession']);
        Route::delete('/account', [CustomerAccountController::class, 'destroy']);
        Route::post('/logout', [CustomerAuthController::class, 'logout']);
        Route::post('/logout-all', [CustomerAuthController::class, 'logoutAll']);
    });
    Route::middleware(['admin.token.active', 'auth:sanctum', 'admin.access'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/email/change', [AuthController::class, 'requestEmailChange'])->middleware('throttle:3,10');
        Route::post('/auth/email/verify', [AuthController::class, 'verifyEmailChange'])->middleware('throttle:10,1');
        Route::patch('/auth/password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1');
        Route::get('/auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('/auth/sessions/{tokenId}', [AuthController::class, 'destroySession']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    });

    Route::middleware(['admin.token.active', 'auth:sanctum', 'admin.access'])->prefix('admin')->group(function (): void {
        Route::get('/businesses/{business}/bookings', [AdminBookingController::class, 'index']);
        Route::get('/businesses/{business}/summary', [AdminBookingController::class, 'summary']);
        Route::get('/businesses/{business}/today', [AdminBookingController::class, 'today']);
        Route::get('/businesses/{business}/calendar', [AdminBookingController::class, 'calendar']);
        Route::get('/businesses/{business}/statistics', [AdminReportController::class, 'statistics']);
        Route::get('/businesses/{business}/exports/bookings', [AdminReportController::class, 'exportBookings']);
        Route::get('/businesses/{business}/exports/statistics', [AdminReportController::class, 'exportStatistics']);
        Route::get('/businesses/{business}/availability-calendar', [AdminBookingController::class, 'availabilityCalendar']);
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
        Route::get('/businesses/{business}/working-hours', [AdminScheduleController::class, 'show']);
        Route::put('/businesses/{business}/working-hours', [AdminScheduleController::class, 'update']);
        Route::patch('/bookings/{booking}/status', [AdminBookingController::class, 'updateStatus']);
        Route::post('/bookings/{booking}/anonymize', [AdminBookingController::class, 'anonymize']);
        Route::get('/businesses/{business}/email-logs', [AdminEmailController::class, 'index']);
        Route::get('/businesses/{business}/email-settings', [AdminEmailController::class, 'showSettings']);
        Route::patch('/businesses/{business}/email-settings', [AdminEmailController::class, 'updateSettings']);
        Route::post('/businesses/{business}/email-test', [AdminEmailController::class, 'sendTest']);
        Route::post('/email-logs/{emailLog}/resend', [AdminEmailController::class, 'resend']);
        Route::get('/businesses/{business}/reminder-logs', [AdminReminderController::class, 'index']);
        Route::post('/businesses/{business}/reminders/dispatch', [AdminReminderController::class, 'dispatchNow']);
        Route::get('/businesses/{business}/customers', [AdminCustomerController::class, 'index']);
        Route::get('/customers/{customerProfile}', [AdminCustomerController::class, 'show']);
        Route::patch('/customers/{customerProfile}', [AdminCustomerController::class, 'update']);
        Route::get('/businesses/{business}/settings', [AdminSettingsController::class, 'show']);
        Route::patch('/businesses/{business}/settings', [AdminSettingsController::class, 'update']);

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
