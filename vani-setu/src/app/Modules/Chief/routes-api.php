<?php

use App\Modules\Chief\Controllers\ChiefConsolidationController;
use Illuminate\Support\Facades\Route;

Route::prefix('chief')->middleware(['auth:sanctum', 'role:chief'])->group(function () {
    Route::get('/queue', [ChiefConsolidationController::class, 'queue']);
    Route::get('/consolidations/{consolidation}', [ChiefConsolidationController::class, 'show']);
    Route::put('/consolidations/{consolidation}/blocks/{block}', [ChiefConsolidationController::class, 'updateBlock']);
    Route::put('/consolidations/{consolidation}/blocks/{block}/speaker', [ChiefConsolidationController::class, 'updateSpeaker']);
    Route::post('/consolidations/{consolidation}/commit', [ChiefConsolidationController::class, 'commit']);
    Route::post('/consolidations/{consolidation}/return', [ChiefConsolidationController::class, 'return']);
    Route::get('/consolidations/{consolidation}/history', [ChiefConsolidationController::class, 'history']);
});
