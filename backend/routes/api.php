<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\RepairTicketController;
use App\Http\Controllers\AttachmentController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is working',
        ]);
    });

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);

        Route::middleware(EnsureUserIsActive::class)->group(function () {

            Route::get('/me', [AuthController::class, 'me']);

            Route::get('/tickets', [RepairTicketController::class,'index']);

            Route::get('/tickets/{ticket}', [RepairTicketController::class,'show']);

            Route::post('/tickets', [RepairTicketController::class, 'store']);

            Route::post('/tickets/{ticket}/cancel', [RepairTicketController::class,'cancel']);

            Route::post('/tickets/{ticket}/claim', [RepairTicketController::class,'claim']);
            
            Route::post('/tickets/{ticket}/work-logs', [RepairTicketController::class,'addWorkLog']);

            Route::post('/tickets/{ticket}/information-requests', [RepairTicketController::class,'requestInformation']);

            Route::post('/tickets/{ticket}/information-requests/{informationRequest}/respond',[RepairTicketController::class,'respondInformation']);

            Route::get('/tickets/{ticket}/notes', [RepairTicketController::class,'notes',]);

            Route::get('/attachments/{attachment}/download', [AttachmentController::class,'download',])->name('attachments.download');
        });
    });
});