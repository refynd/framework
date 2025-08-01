<?php

use Refynd\Http\RouteFacade as Route;

/**
 * API Routes for {{APP_NAME}}
 */

// Health check
Route::get('/health', function() {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => '1.0.0'
    ]);
});

// API v1 routes
Route::group(['prefix' => 'api/v1'], function() {
    
    // Example API endpoints
    Route::get('/users', [\{{APP_NAMESPACE}}\Http\Controllers\UserController::class, 'index']);
    Route::post('/users', [\{{APP_NAMESPACE}}\Http\Controllers\UserController::class, 'store']);
    Route::get('/users/{id}', [\{{APP_NAMESPACE}}\Http\Controllers\UserController::class, 'show']);
    Route::put('/users/{id}', [\{{APP_NAMESPACE}}\Http\Controllers\UserController::class, 'update']);
    Route::delete('/users/{id}', [\{{APP_NAMESPACE}}\Http\Controllers\UserController::class, 'destroy']);
    
});
