<?php

use App\Modules\SgDash\Controllers\SgDashController;
use Illuminate\Support\Facades\Route;

Route::prefix('sg-dash')->middleware(['auth:sanctum', 'role:sg'])->group(function () {
    Route::get('/dates', [SgDashController::class, 'dates']);
    Route::get('/pipeline', [SgDashController::class, 'pipeline']);
    Route::get('/ageing', [SgDashController::class, 'ageing']);
    Route::get('/feed', [SgDashController::class, 'feed']);
    Route::get('/windows', [SgDashController::class, 'windows']);
    Route::get('/windows/{window}', [SgDashController::class, 'show']);
});
