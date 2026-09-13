<?php

use App\Http\Controllers\Api\V1\AIController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReceivablesController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Middleware\EnsureActiveBusiness;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NiagaKu REST API v1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Health Check Endpoint
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'app' => 'NiagaKu API',
            'version' => '1.0.0',
            'status' => 'operational',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Public Auth Routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Payment Gateway Webhook Listener (Public)
    Route::post('/subscription/webhook', [SubscriptionController::class, 'webhook']);

    // Authenticated User Routes (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // Onboarding (Create first business)
        Route::post('/business/onboarding', [BusinessController::class, 'store']);

        // Tenant Scoped Routes (Requires active business)
        Route::middleware(EnsureActiveBusiness::class)->group(function () {
            // Dashboard Analytics & KPI Endpoint
            Route::get('/dashboard', [DashboardController::class, 'index']);

            // Business Management
            Route::prefix('business')->group(function () {
                Route::get('/', [BusinessController::class, 'show']);
                Route::put('/', [BusinessController::class, 'update']);
                Route::get('/financial-access-check', [BusinessController::class, 'financialAccessCheck']);
            });

            // Subscription & Billing Management
            Route::prefix('subscription')->group(function () {
                Route::get('/plans', [SubscriptionController::class, 'plans']);
                Route::get('/current', [SubscriptionController::class, 'current']);
                Route::post('/checkout', [SubscriptionController::class, 'checkout']);
            });

            // NiagaKu AI Module
            Route::prefix('ai')->group(function () {
                Route::get('/conversations', [AIController::class, 'index']);
                Route::post('/conversations', [AIController::class, 'store']);
                Route::get('/conversations/{conversation}', [AIController::class, 'show']);
                Route::post('/conversations/{conversation}/messages', [AIController::class, 'sendMessage']);
            });

            // Customers Module
            Route::apiResource('customers', CustomerController::class);

            // Categories Module
            Route::get('/categories', [CategoryController::class, 'index']);
            Route::post('/categories', [CategoryController::class, 'store']);

            // Products & Inventory Module
            Route::post('/products/{product}/stock', [ProductController::class, 'adjustStock']);
            Route::apiResource('products', ProductController::class);

            // Orders Module
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
            Route::post('/orders/{order}/status', [OrderController::class, 'updateStatus']);
            Route::get('/orders/{order}/payments', [PaymentController::class, 'indexForOrder']);
            Route::post('/orders/{order}/payments', [PaymentController::class, 'storeForOrder']);
            Route::apiResource('orders', OrderController::class);

            // Payments Module
            Route::get('/payments', [PaymentController::class, 'index']);

            // Expenses Module
            Route::apiResource('expenses', ExpenseController::class);

            // Receivables Module
            Route::get('/receivables', [ReceivablesController::class, 'index']);

            // Reports & Export Module
            Route::get('/reports', [ReportController::class, 'index']);
            Route::get('/reports/export', [ReportController::class, 'export']);

            // Notifications & Alerts Module
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        });
    });
});
