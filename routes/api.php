<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\DoctorVerificationController;
use App\Http\Controllers\LoginActivityController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SkincareProductController;
use App\Http\Controllers\SkinConcernController;
use App\Http\Controllers\SkinRecommendationController;
use App\Http\Controllers\SkinTypeController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'data' => [
        'name' => config('app.name'),
        'status' => 'ok',
        'current_version' => 'v1',
        'version_url' => url('/api/v1'),
    ],
    'meta' => (object) [],
]));

Route::prefix('v1')->group(function () {
    Route::get('/', fn () => response()->json([
        'data' => [
            'name' => config('app.name'),
            'version' => 'v1',
            'status' => 'ok',
        ],
        'meta' => (object) [],
    ]));

    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/auth/google', [AuthController::class, 'google'])->middleware('throttle:google-auth');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:password-reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset');

    Route::post('/webhooks/midtrans', [WebhookController::class, 'handleMidtrans']);

    // Catalog (public read)
    Route::get('/skin-concerns', [SkinConcernController::class, 'index']);
    Route::get('/skin-concerns/{skinConcern}', [SkinConcernController::class, 'show']);
    Route::get('/skin-types', [SkinTypeController::class, 'index']);
    Route::get('/skin-types/{skinType}', [SkinTypeController::class, 'show']);
    Route::get('/products', [SkincareProductController::class, 'index']);
    Route::get('/products/{skincareProduct}', [SkincareProductController::class, 'show']);
    Route::get('/recommendations', [SkinRecommendationController::class, 'index']);
    Route::get('/recommendations/{skinRecommendation}', [SkinRecommendationController::class, 'show']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);

        Route::get('/login-activity', [LoginActivityController::class, 'index']);
        Route::delete('/login-activity/{personalAccessToken}', [LoginActivityController::class, 'destroy']);

        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
        Route::get('/subscriptions/{subscription}/receipt', [SubscriptionController::class, 'receipt']);

        Route::get('/scans', [ScanController::class, 'index']);
        Route::post('/scans', [ScanController::class, 'store']);
        Route::post('/scans/livecam', [ScanController::class, 'storeLivecam']);
        Route::get('/scans/{predictionHistory}', [ScanController::class, 'show']);

        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy']);

        Route::post('/conversations', [ConversationController::class, 'store']);
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage']);

        // Doctor verification
        Route::middleware('role:doctor')->group(function () {
            Route::get('/doctor-verification', [DoctorVerificationController::class, 'show']);
            Route::post('/doctor-verification', [DoctorVerificationController::class, 'submit']);
            Route::post('/doctor-verification/resubmit', [DoctorVerificationController::class, 'resubmit']);
        });

        // Admin routes
        Route::middleware('role:admin')->group(function () {
            Route::get('/admin/users', [AdminController::class, 'listUsers']);
            Route::patch('/admin/users/{user}/role', [AdminController::class, 'assignRole']);
            Route::get('/admin/doctor-verifications', [DoctorVerificationController::class, 'index']);
            Route::patch('/admin/doctor-verifications/{doctorVerification}', [DoctorVerificationController::class, 'review']);

            Route::apiResource('admin/skin-concerns', SkinConcernController::class);
            Route::apiResource('admin/skin-types', SkinTypeController::class);
        });

        // Product & recommendation management (doctor)
        Route::middleware('role:doctor')->group(function () {
            Route::post('/products', [SkincareProductController::class, 'store']);
            Route::patch('/products/{skincareProduct}', [SkincareProductController::class, 'update']);
            Route::delete('/products/{skincareProduct}', [SkincareProductController::class, 'destroy']);

            Route::post('/recommendations', [SkinRecommendationController::class, 'store']);
            Route::patch('/recommendations/{skinRecommendation}', [SkinRecommendationController::class, 'update']);
            Route::delete('/recommendations/{skinRecommendation}', [SkinRecommendationController::class, 'destroy']);
        });
    });
});
