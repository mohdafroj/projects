<?php

use App\Modules\LiveChamber\Controllers\LiveChamberController;
use Illuminate\Support\Facades\Route;

Route::prefix('live-chamber')
    ->middleware(['auth:sanctum', 'role:admin|reporter|supervisor|chief|js|sg|director|translator'])
    ->group(function () {
        Route::get('/snapshot', [LiveChamberController::class, 'snapshot']);
    });
