<?php

use App\Modules\ApprovalQueue\Controllers\ApprovalQueueController;
use Illuminate\Support\Facades\Route;

Route::prefix('approval-queue')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/items', [ApprovalQueueController::class, 'index']);
        Route::get('/summary', [ApprovalQueueController::class, 'summary']);
        Route::get('/items/{itemKey}', [ApprovalQueueController::class, 'show'])->where('itemKey', '.*');
        Route::post('/items/{itemKey}/acknowledge', [ApprovalQueueController::class, 'acknowledge'])->where('itemKey', '.*');
        Route::post('/items/{itemKey}/snooze', [ApprovalQueueController::class, 'snooze'])->where('itemKey', '.*');
        Route::delete('/items/{itemKey}/acknowledgement', [ApprovalQueueController::class, 'clear'])->where('itemKey', '.*');
    });
