<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AdminController;

// Auth
Route::prefix('auth')->group(function () {
    Route::match(['get', 'post'], '/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/address', [AuthController::class, 'updateAddress']);
    Route::post('/avatar', [AuthController::class, 'uploadAvatar']);
});

// Seller
Route::get('/seller/profile', [SellerController::class, 'getProfile']);
Route::put('/seller/profile', [SellerController::class, 'updateProfile']);
Route::get('/seller/stats', [SellerController::class, 'stats']);

// Categories
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

// Products
Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);
Route::post('/products/{id}/approve', [ProductController::class, 'approve']);
Route::post('/products/{id}/review', [ProductController::class, 'review']);

// Product documents (file upload)
Route::get('/uploads/{path?}', [ProductController::class, 'serveDocument'])->where('path', '.*');
Route::get('/products/{id}/documents', [ProductController::class, 'documents']);
Route::post('/products/{id}/documents', [ProductController::class, 'uploadDocument']);

// Orders
Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
Route::post('/orders/{id}/packing-photo', [OrderController::class, 'uploadPackingPhoto']);
Route::post('/orders/{id}/release', [OrderController::class, 'releaseEscrowEndpoint']);

// Payments
Route::get('/payments/{order_id}', [PaymentController::class, 'show']);
Route::post('/payments/{order_id}/pay', [PaymentController::class, 'pay']);
Route::post('/payments/{order_id}/release', [PaymentController::class, 'release']);

// Disputes
Route::get('/disputes', [DisputeController::class, 'index']);
Route::post('/disputes', [DisputeController::class, 'store']);
Route::get('/disputes/{id}/messages', [DisputeController::class, 'getMessages']);
Route::post('/disputes/{id}/messages', [DisputeController::class, 'send']);
Route::post('/disputes/{id}/resolve', [DisputeController::class, 'resolve']);

// Reviews
Route::get('/reviews', [ReviewController::class, 'index']);
Route::post('/reviews', [ReviewController::class, 'store']);

// Messages
Route::get('/messages/conversations', [MessageController::class, 'conversations']);
Route::get('/messages/{conversation_id}', [MessageController::class, 'getMessages']);
Route::post('/messages', [MessageController::class, 'send']);
Route::post('/messages/negotiation', [MessageController::class, 'sendNegotiation']);
Route::post('/messages/negotiation/accept', [MessageController::class, 'acceptNego']);
Route::post('/messages/negotiation/reject', [MessageController::class, 'rejectNego']);
Route::post('/messages/negotiation/counter', [MessageController::class, 'counterNego']);

// Notifications
Route::get('/notifications', [NotificationController::class, 'index']);
Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);

// Admin
Route::get('/admin/stats', [AdminController::class, 'stats']);
Route::get('/admin/users', [AdminController::class, 'users']);
