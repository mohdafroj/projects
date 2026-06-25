<?php

use App\Modules\Synopsis\Controllers\SynopsisStudioController;
use Illuminate\Support\Facades\Route;

Route::prefix('synopsis')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/queue', [SynopsisStudioController::class, 'queue']);
    Route::get('/chunks/{consolidation}', [SynopsisStudioController::class, 'show']);
    Route::post('/chunks/{consolidation}/generate', [SynopsisStudioController::class, 'generate']);
    Route::post('/chunks/{consolidation}/generate-from-text', [SynopsisStudioController::class, 'generateFromText']);
    Route::post('/chunks/{consolidation}/author', [SynopsisStudioController::class, 'author']);
    Route::put('/chunks/{consolidation}/draft', [SynopsisStudioController::class, 'save']);
    Route::post('/chunks/{consolidation}/submit', [SynopsisStudioController::class, 'submit']);
    Route::post('/chunks/{consolidation}/finalise', [SynopsisStudioController::class, 'finalise']);
    Route::get('/chunks/{consolidation}/export.pdf', [SynopsisStudioController::class, 'exportPdf']);
    Route::get('/chunks/{consolidation}/history', [SynopsisStudioController::class, 'history']);
});
