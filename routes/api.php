<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController as Auth, MarketplaceController as Market, AccountController as Account, BookingController as Booking, AdminController as Admin};
Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/signup', [Auth::class, 'signup']);
    Route::post('/auth/login', [Auth::class, 'login']);
    Route::post('/auth/forgot-password', [Auth::class, 'forgot']);
    Route::post('/auth/reset-password', [Auth::class, 'reset']);
    Route::post('/admin/login', [Auth::class, 'adminLogin']);
});
Route::get('/chefs', [Market::class, 'chefs']);
Route::get('/chefs/{id}', [Market::class, 'chef'])->whereNumber('id');
Route::get('/chefs/{id}/reviews', [Market::class, 'reviews'])->whereNumber('id');
Route::get('/chefs/{id}/availability', [Market::class, 'availability'])->whereNumber('id');
Route::get('/services', [Market::class, 'services']);
Route::get('/services/{id}', [Market::class, 'service'])->whereNumber('id');
Route::get('/public/stats', [Market::class, 'stats']);
Route::post('/contact', [Market::class, 'contact'])->middleware('throttle:contact');
Route::get('/bookings/availability', [Booking::class, 'availability']);
Route::middleware(['auth:sanctum'])->post('/auth/logout', [Auth::class, 'logout']);
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/auth/me', [Auth::class, 'me']);
    Route::post('/auth/resend-verification', [Auth::class, 'resendVerification'])->middleware('throttle:3,1');
    Route::get('/users/profile', [Account::class, 'profile']);
    Route::put('/users/profile', [Account::class, 'update']);
    Route::put('/users/change-password', [Account::class, 'changePassword']);
    Route::post('/users/upload-avatar', [Account::class, 'avatar']);
    Route::get('/bookings', [Booking::class, 'index']);
    Route::get('/bookings/{id}', [Booking::class, 'show'])->whereNumber('id');
    Route::put('/bookings/{id}', [Booking::class, 'update'])->whereNumber('id');
    Route::delete('/bookings/{id}', [Booking::class, 'cancel'])->whereNumber('id');
    Route::get('/payments/{id}', [Booking::class, 'payments'])->whereNumber('id');
    Route::post('/payments/verify', [Booking::class, 'verifyPayment']);
    Route::get('/reviews/my', [Booking::class, 'myReviews']);
    Route::middleware('role:customer')->group(function () {
        Route::post('/bookings', [Booking::class, 'store']);
        Route::post('/payments', [Booking::class, 'payment']);
        Route::post('/reviews', [Booking::class, 'review']);
    });
    Route::middleware('role:chef')->group(function () {
        Route::get('/chef/profile', [Account::class, 'chef']);
        Route::put('/chef/profile', [Account::class, 'updateChef']);
        Route::put('/chef/availability', [Account::class, 'schedule']);
        Route::post('/chefs/apply', [Account::class, 'apply']);
        Route::get('/chef/earnings', [Account::class, 'earnings']);
    });
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/stats', [Admin::class, 'stats']);
        Route::get('/users', [Admin::class, 'users']);
        Route::put('/users/{id}/status', [Admin::class, 'userStatus']);
        Route::get('/chefs', [Admin::class, 'chefs']);
        Route::put('/chefs/{id}/verify', [Admin::class, 'verify']);
        Route::get('/chefs/{id}/documents', [Admin::class, 'documents']);
        Route::get('/bookings', [Admin::class, 'bookings']);
        Route::put('/bookings/{id}/status', [Admin::class, 'bookingStatus']);
        Route::get('/payments', [Admin::class, 'payments']);
        Route::put('/payments/{id}/refund', [Admin::class, 'refund']);
        Route::get('/reviews', [Admin::class, 'reviews']);
        Route::delete('/reviews/{id}', [Admin::class, 'deleteReview']);
        Route::get('/recent-activity', [Admin::class, 'activity']);
        Route::get('/reports/revenue', [Admin::class, 'revenue']);
        Route::get('/contacts', [Admin::class, 'contacts']);
        Route::put('/contacts/{id}/read', [Admin::class, 'readContact']);
    });
});
