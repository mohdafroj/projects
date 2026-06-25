<?php

use App\Modules\Translator\Controllers\TranslatorController;
use Illuminate\Support\Facades\Route;

Route::prefix('translator')->middleware(['auth:sanctum', 'role:translator'])->group(function () {
    Route::get('/queue', [TranslatorController::class, 'queue']);
    Route::get('/slot/{slot}/draft', [TranslatorController::class, 'slotDraft']);
    Route::patch('/slot/{slot}/draft', [TranslatorController::class, 'patchSlotDraft']);
    Route::post('/slot/{slot}/finalise', [TranslatorController::class, 'finaliseSlotDraft']);
    Route::post('/slot/{slot}/commit', [TranslatorController::class, 'commitSlotDraft']);
    Route::get('/assignments/{assignment}', [TranslatorController::class, 'show']);
    Route::post('/assignments/{assignment}/request-ai', [TranslatorController::class, 'requestAi']);
    Route::put('/assignments/{assignment}/blocks/{block}', [TranslatorController::class, 'updateBlock']);
    Route::post('/assignments/{assignment}/blocks/{block}/accept-ai', [TranslatorController::class, 'acceptAi']);
    Route::post('/assignments/{assignment}/commit', [TranslatorController::class, 'commit']);
    Route::post('/assignments/{assignment}/forward-supervisor', [TranslatorController::class, 'forwardToSupervisor']);
    Route::post('/assignments/{assignment}/return', [TranslatorController::class, 'return']);
    Route::get('/assignments/{assignment}/history', [TranslatorController::class, 'history']);
    Route::get('/glossary', [TranslatorController::class, 'glossary']);
    Route::post('/glossary', [TranslatorController::class, 'storeGlossary']);
    Route::put('/glossary/{glossary}', [TranslatorController::class, 'updateGlossary']);
});

Route::prefix('translator/reviewer')->middleware(['auth:sanctum', 'role:supervisor|admin'])->group(function () {
    Route::get('/queue', [TranslatorController::class, 'reviewerQueue']);
    Route::get('/assignments/{assignment}', [TranslatorController::class, 'reviewerShow']);
    Route::post('/assignments/{assignment}/forward-director', [TranslatorController::class, 'forwardToDirector']);
    Route::post('/assignments/{assignment}/return-translator', [TranslatorController::class, 'reviewerReturn']);
});
