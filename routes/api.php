<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DoctorRatingController;
use App\Http\Controllers\DoctorVerificationController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\LoginActivityController;
use App\Http\Controllers\NotificationController;
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
        'version' => 'v1',
        'status' => 'ok',
    ],
    'meta' => (object) [],
]));

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/', fn () => response()->json([
        'data' => [
            'name' => config('app.name'),
            'version' => 'v1',
            'status' => 'ok',
        ],
        'meta' => (object) [],
    ]));

    // Public routes
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/register-doctor', [AuthController::class, 'registerDoctor'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('/auth/google', [AuthController::class, 'google'])->middleware('throttle:google-auth');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:reset-password');

    Route::get('/emergency', fn () => response()->json([
        'data' => config('emergency.hotlines', []),
        'meta' => (object) [],
    ]));

    Route::match(['get', 'post'], '/webhooks/midtrans', [WebhookController::class, 'handleMidtrans']);

    // Catalog (public read)
    Route::get('/skincare-products', [SkincareProductController::class, 'index']);
    Route::get('/skincare-products/{skincareProduct}', [SkincareProductController::class, 'show']);
    Route::get('/skin-recommendations', [SkinRecommendationController::class, 'index']);
    Route::get('/skin-recommendations/{skinRecommendation}', [SkinRecommendationController::class, 'show']);
    Route::get('/skin-concerns', [SkinConcernController::class, 'index']);
    Route::get('/skin-concerns/{skinConcern}', [SkinConcernController::class, 'show']);
    Route::get('/skin-types', [SkinTypeController::class, 'index']);
    Route::get('/skin-types/{skinType}', [SkinTypeController::class, 'show']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);

        Route::post('/email/verify/send', [EmailVerificationController::class, 'send']);
        Route::post('/email/verify', [EmailVerificationController::class, 'verify']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
        Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar']);
        Route::post('/profile/export', [ProfileController::class, 'export']);
        Route::get('/profile/exports/download', [ProfileController::class, 'downloadExport'])->name('profile.export.download');

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

        Route::get('/ai-chat/consent', [AiChatController::class, 'consent']);
        Route::post('/ai-chat/consent', [AiChatController::class, 'updateConsent']);
        Route::post('/ai-chat/conversations', [AiChatController::class, 'startConversation']);
        Route::delete('/ai-chat/conversations/{conversation}', [AiChatController::class, 'destroyConversation']);

        Route::get('/login-activity', [LoginActivityController::class, 'index']);
        Route::delete('/login-activity/{personalAccessToken}', [LoginActivityController::class, 'destroy']);

        Route::get('/doctors', [DoctorController::class, 'index'])->name('doctors.index');
        Route::get('/doctors/{doctor}', [DoctorController::class, 'show'])->name('doctors.show');
        Route::post('/doctors/{doctor}/ratings', [DoctorRatingController::class, 'store']);
        Route::get('/doctors/{doctor}/ratings', [DoctorRatingController::class, 'index']);

        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
        Route::get('/subscriptions/{subscription}/receipt', [SubscriptionController::class, 'receipt']);
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);

        Route::get('/scans', [ScanController::class, 'index']);
        Route::post('/scans', [ScanController::class, 'store'])->middleware('throttle:scans');
        Route::post('/scans/livecam', [ScanController::class, 'storeLivecam'])->middleware('throttle:scans');
        Route::get('/scans/{predictionHistory}', [ScanController::class, 'show']);
        Route::post('/scans/{predictionHistory}/feedback', [ScanController::class, 'feedback']);

        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy']);

        Route::post('/conversations', [ConversationController::class, 'store']);
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage']);

        // Doctor verification
        Route::middleware('role:doctor')->group(function () {
            Route::get('/doctor-verifications', [DoctorVerificationController::class, 'show']);
            Route::post('/doctor-verifications', [DoctorVerificationController::class, 'submit']);
            Route::post('/doctor-verifications/{doctorVerification}/resubmit', [DoctorVerificationController::class, 'resubmit']);
        });

        // Admin routes
        Route::middleware('role:admin')->group(function () {
            Route::get('/admin/users', [AdminController::class, 'listUsers']);
            Route::get('/admin/users/{user}', [AdminController::class, 'showUser']);
            Route::patch('/admin/users/{user}/role', [AdminController::class, 'assignRole']);
            Route::get('/admin/activity-log', [AdminController::class, 'activityLog']);
            Route::get('/admin/verifications', [DoctorVerificationController::class, 'index']);
            Route::get('/admin/verifications/{doctorVerification}', [DoctorVerificationController::class, 'showAdmin']);
            Route::patch('/doctor-verifications/{doctorVerification}/review', [DoctorVerificationController::class, 'review']);

            Route::post('/skin-concerns', [SkinConcernController::class, 'store']);
            Route::patch('/skin-concerns/{skinConcern}', [SkinConcernController::class, 'update']);
            Route::delete('/skin-concerns/{skinConcern}', [SkinConcernController::class, 'destroy']);
            Route::post('/skin-types', [SkinTypeController::class, 'store']);
            Route::patch('/skin-types/{skinType}', [SkinTypeController::class, 'update']);
            Route::delete('/skin-types/{skinType}', [SkinTypeController::class, 'destroy']);
            Route::get('/admin/skincare-products', [SkincareProductController::class, 'adminIndex']);
        });

        // Product & recommendation management (doctor)
        Route::middleware('role:doctor')->group(function () {
            Route::get('/doctor/products', [SkincareProductController::class, 'doctorIndex']);
            Route::post('/skincare-products', [SkincareProductController::class, 'store']);
            Route::patch('/skincare-products/{skincareProduct}', [SkincareProductController::class, 'update']);
            Route::delete('/skincare-products/{skincareProduct}', [SkincareProductController::class, 'destroy']);

            Route::get('/doctor/recommendations', [SkinRecommendationController::class, 'doctorIndex']);
            Route::post('/skin-recommendations', [SkinRecommendationController::class, 'store']);
            Route::patch('/skin-recommendations/{skinRecommendation}', [SkinRecommendationController::class, 'update']);
            Route::delete('/skin-recommendations/{skinRecommendation}', [SkinRecommendationController::class, 'destroy']);
        });
    });
});
