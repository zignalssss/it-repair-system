<?php
use Illuminate\Support\Facades\Route;

Route::get('/v1/healthz', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working'
        ]);
});