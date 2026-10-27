<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ProfileController;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Secure Profile Routes
    Route::post('/user/setup-pin', [ProfileController::class, 'setupPin']);
    Route::post('/user/verify-pin', [ProfileController::class, 'verifyPin']);
    Route::post('/user/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/user/push-token', [ProfileController::class, 'updatePushToken']);

    Route::get('/payment-methods/manual-bank', [App\Http\Controllers\Api\ManualBankAccountController::class, 'show']);

    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/rooms/{room}', [RoomController::class, 'show']);

    Route::get('/services', [ServiceController::class, 'index']);
    
    Route::get('/products', [App\Http\Controllers\Api\ProductController::class, 'index']);
    Route::get('/products/{product}', [App\Http\Controllers\Api\ProductController::class, 'show']);

    // Order related routes
    Route::get('/orders', [App\Http\Controllers\Api\OrderController::class, 'index']);
    Route::post('/orders', [App\Http\Controllers\Api\OrderController::class, 'store']);

    // Dining (Food Ordering) related routes
    Route::get('/menu/categories', [App\Http\Controllers\Api\FoodMenuController::class, 'categories']);
    Route::get('/menu', [App\Http\Controllers\Api\FoodMenuController::class, 'index']);
    Route::post('/food-orders', [App\Http\Controllers\Api\FoodOrderController::class, 'store']);
    Route::get('/food-orders/{id}', [App\Http\Controllers\Api\FoodOrderController::class, 'show']);

    // Booking related routes
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'index']);
    
    // Service Booking related routes
    Route::get('/service-bookings', [App\Http\Controllers\Api\ServiceBookingController::class, 'index']);
    Route::post('/service-bookings', [App\Http\Controllers\Api\ServiceBookingController::class, 'store']);

    // Notifications
    Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

    // Apartment Management — Guest Registration & Maintenance
    Route::get('/guests', [App\Http\Controllers\Api\ApartmentController::class, 'listGuests']);
    Route::post('/guests', [App\Http\Controllers\Api\ApartmentController::class, 'registerGuest']);
    Route::get('/maintenance', [App\Http\Controllers\Api\ApartmentController::class, 'listMaintenance']);
    Route::post('/maintenance', [App\Http\Controllers\Api\ApartmentController::class, 'submitMaintenance']);

    // Flutterwave & Online Payment Gateway Integration
    Route::post('/payments/initialize', [App\Http\Controllers\Api\PaymentController::class, 'initializeFlutterwave']);
    Route::get('/payments/verify/{reference}', [App\Http\Controllers\Api\PaymentController::class, 'verifyFlutterwave']);
    Route::post('/payments/flutterwave/initialize', [App\Http\Controllers\Api\PaymentController::class, 'initializeFlutterwave']);
    Route::get('/payments/flutterwave/verify/{reference}', [App\Http\Controllers\Api\PaymentController::class, 'verifyFlutterwave']);

    // Historical OPay transactions remain in the database; new OPay checkouts are disabled.
});

// Flutterwave Server-to-Server Webhook (Public Callback)
Route::post('/payments/flutterwave/webhook', [App\Http\Controllers\Api\PaymentController::class, 'webhookFlutterwave']);
Route::post('/payments/webhook/flutterwave', [App\Http\Controllers\Api\PaymentController::class, 'webhookFlutterwave']);

// Legacy OPay webhook intentionally disabled; historical transaction data is preserved.

// Public access for guests (optional, depending on requirements)
Route::get('/rooms-public', [RoomController::class, 'index']);
Route::get('/services-public', [ServiceController::class, 'index']);
