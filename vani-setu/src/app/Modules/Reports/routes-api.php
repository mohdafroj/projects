<?php

use App\Modules\Reports\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->middleware(['auth:sanctum', 'role:admin|chief|js|sg'])->group(function () {
    Route::get('/summary', [ReportsController::class, 'summary']);
    Route::get('/charts', [ReportsController::class, 'charts']);
    Route::post('/snapshots', [ReportsController::class, 'captureSnapshot']);
    Route::get('/export', [ReportsController::class, 'export']);
});
