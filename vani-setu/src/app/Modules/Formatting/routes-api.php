<?php

use App\Modules\Formatting\Controllers\FormattingController;
use Illuminate\Support\Facades\Route;

Route::prefix('formatting')->middleware(['auth:sanctum', 'role:formatting'])->group(function () {
    Route::get('/jobs', [FormattingController::class, 'queue']);
    Route::post('/jobs', [FormattingController::class, 'create']);
    Route::get('/jobs/{job}', [FormattingController::class, 'show']);
    Route::post('/jobs/{job}/validate', [FormattingController::class, 'validateJob']);
    Route::post('/jobs/{job}/crc', [FormattingController::class, 'crc']);
    Route::post('/jobs/{job}/dispatch', [FormattingController::class, 'dispatch']);
    Route::get('/jobs/{job}/audit', [FormattingController::class, 'audit']);
});
