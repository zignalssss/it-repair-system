<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/healthz', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is working',
        ]);
    });

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])
            ->middleware(EnsureUserIsActive::class);

        Route::post('/logout', [AuthController::class, 'logout']);
    });
});