<?php

use App\Modules\MasterDash\Controllers\MasterDashController;
use Illuminate\Support\Facades\Route;

Route::prefix('master-dash')
    ->middleware(['auth:sanctum', 'role:admin|reporter|supervisor|chief|js|sg|director|translator'])
    ->group(function () {
        Route::get('/overview', [MasterDashController::class, 'overview']);
        Route::get('/pendency', [MasterDashController::class, 'pendency']);
        Route::get('/roster', [MasterDashController::class, 'roster']);
    });
