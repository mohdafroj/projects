<?php

use App\Modules\Regional\Controllers\RegionalController;
use Illuminate\Support\Facades\Route;

Route::prefix('regional')->middleware(['auth:sanctum', 'role:translator'])->group(function () {
    Route::get('/queue', [RegionalController::class, 'queue']);
    Route::post('/cases', [RegionalController::class, 'store']);
    Route::get('/cases/{case}', [RegionalController::class, 'show']);
    Route::post('/cases/{case}/translate', [RegionalController::class, 'translate']);
    Route::post('/cases/{case}/cross-check', [RegionalController::class, 'crossCheck']);
    Route::post('/cases/{case}/commit', [RegionalController::class, 'commit']);
    Route::get('/cases/{case}/history', [RegionalController::class, 'history']);
});
