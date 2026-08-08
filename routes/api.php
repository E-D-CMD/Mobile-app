<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health', function () {
    return response()->json([
        'status'    => 'ok',
        'service'   => 'PerfumeHub API',
        'timestamp' => now(),
    ]);
});

// Products
Route::prefix('products')->group(function () {
    Route::get('/',                ProductController::class . '@index');
    Route::get('/filters/options', ProductController::class . '@filterOptions');
    Route::get('/{product}',       ProductController::class . '@show');
});

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',       [AuthController::class, 'me']);
        Route::post('/logout',  [AuthController::class, 'logout']);
    });
});

// Protected routes (require Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Order tracking
    Route::prefix('orders')->group(function () {
        Route::get('/',        [OrderController::class, 'index']);
        Route::post('/',       [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
    });
});

// Admin dashboard — every route here requires a valid Sanctum token AND
// role == 'admin' (see app/Http/Middleware/EnsureUserIsAdmin.php). A
// logged-in customer hitting any of these gets a clean 403.
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    Route::prefix('products')->group(function () {
        Route::get('/',                    [AdminProductController::class, 'index']);
        Route::post('/',                   [AdminProductController::class, 'store']);
        Route::get('/{product}',           [AdminProductController::class, 'show']);
        Route::match(['put', 'post'], '/{product}', [AdminProductController::class, 'update']);
        Route::delete('/{product}',        [AdminProductController::class, 'destroy']);
        Route::put('/{product}/status',    [AdminProductController::class, 'toggleStatus']);
        Route::put('/{product}/stock',     [AdminProductController::class, 'updateStock']);
    });

    Route::prefix('categories')->group(function () {
        Route::get('/',              [AdminCategoryController::class, 'index']);
        Route::post('/',             [AdminCategoryController::class, 'store']);
        Route::put('/{category}',    [AdminCategoryController::class, 'update']);
        Route::delete('/{category}', [AdminCategoryController::class, 'destroy']);
    });

    Route::prefix('orders')->group(function () {
        Route::get('/',           [AdminOrderController::class, 'index']);
        Route::get('/{order}',    [AdminOrderController::class, 'show']);
        Route::put('/{order}',    [AdminOrderController::class, 'update']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/',                [AdminUserController::class, 'index']);
        Route::get('/{user}',          [AdminUserController::class, 'show']);
        Route::put('/{user}/disable',  [AdminUserController::class, 'disable']);
        Route::put('/{user}/enable',   [AdminUserController::class, 'enable']);
    });
});
