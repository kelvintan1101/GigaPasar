<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PlatformConnectionController;
use App\Http\Controllers\ProductSyncController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (authentication required)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Authentication management
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    // Health check for authenticated users
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user()
            ]
        ]);
    });
    
    // Product management routes
    Route::apiResource('products', ProductController::class);
    Route::put('/products/{id}/stock', [ProductController::class, 'updateStock']);
    Route::patch('/products/bulk-status', [ProductController::class, 'bulkUpdateStatus']);
    Route::get('/products-statistics', [ProductController::class, 'statistics']);
    
    // Platform connection routes
    Route::prefix('platform')->group(function () {
        Route::get('/connections', [PlatformConnectionController::class, 'index']);
        Route::get('/connections/stats', [PlatformConnectionController::class, 'getConnectionStats']);
        Route::get('/lazada/auth-url', [PlatformConnectionController::class, 'getLazadaAuthUrl']);
        Route::post('/lazada/callback', [PlatformConnectionController::class, 'handleLazadaCallback']);
    });
});

// Public Lazada callback route (outside authentication)
Route::prefix('v1')->group(function () {
    Route::post('/lazada/callback', [PlatformConnectionController::class, 'handleLazadaCallback']);
        Route::post('/connections/{connectionId}/test', [PlatformConnectionController::class, 'testConnection']);
        Route::delete('/connections/{connectionId}', [PlatformConnectionController::class, 'disconnect']);
    });
    
    // Product sync routes
    Route::prefix('sync')->group(function () {
        Route::post('/products/{productId}', [ProductSyncController::class, 'syncProduct']);
        Route::post('/products/bulk', [ProductSyncController::class, 'bulkSync']);
        Route::get('/products/{productId}/status', [ProductSyncController::class, 'getSyncStatus']);
        Route::get('/statistics', [ProductSyncController::class, 'getSyncStatistics']);
        Route::get('/logs', [ProductSyncController::class, 'getSyncLogs']);
    });
});

// Public Lazada callback route
Route::post('/lazada/callback', [PlatformConnectionController::class, 'handleLazadaCallback']);

// Test route for checking API connectivity
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working',
        'timestamp' => now()->toISOString(),
        'version' => 'v1'
    ]);
});