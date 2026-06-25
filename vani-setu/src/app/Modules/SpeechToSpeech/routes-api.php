<?php

use App\Modules\SpeechToSpeech\Controllers\InternalAudioController;
use App\Modules\SpeechToSpeech\Controllers\S2sController;
use Illuminate\Support\Facades\Route;

// Internal-only segment-audio stream consumed by the recheck engine
// (ml-gateway calls this to fetch a single segment's source audio
// over the docker network). Requests must carry a valid HMAC token
// — see InternalAudioUrlSigner. Deliberately registered outside the
// /api/s2s auth:sanctum group because it is HMAC-authenticated, not
// session-authenticated, and ml-gateway has no session.
Route::get('/internal/s2s/audio/{segmentId}', [InternalAudioController::class, 'show'])
    ->where('segmentId', '[0-9]+')
    ->name('s2s.internal.audio');

Route::prefix('s2s')->middleware(['auth:sanctum', 'role:reporter|chief|js|admin'])->group(function () {
    Route::get('/dashboard', [S2sController::class, 'adminDashboard'])->middleware('role:chief|js|admin');
    Route::get('/public-dashboard', [S2sController::class, 'publicDashboard']);
    Route::get('/config', [S2sController::class, 'configShow'])->middleware('role:chief|js|admin');
    Route::put('/config', [S2sController::class, 'configUpdate'])->middleware('role:chief|js|admin');
    Route::get('/vocabulary', [S2sController::class, 'vocabularyIndex'])->middleware('role:chief|js|admin');
    Route::post('/vocabulary', [S2sController::class, 'vocabularyStore'])->middleware('role:chief|js|admin');
    Route::put('/vocabulary/{id}', [S2sController::class, 'vocabularyUpdate'])->middleware('role:chief|js|admin');

    Route::post('/sessions', [S2sController::class, 'create']);
    Route::get('/sessions/{id}', [S2sController::class, 'show']);
    Route::get('/sessions/{id}/segments', [S2sController::class, 'segments']);
    Route::post('/sessions/{id}/segments', [S2sController::class, 'storeSegment']);
    Route::post('/sessions/{id}/segments/stream', [S2sController::class, 'streamSegment']);
    Route::post('/sessions/{id}/finish', [S2sController::class, 'finish']);
    Route::get('/sessions/{id}/qa-summary', [S2sController::class, 'qaSummary'])
        ->middleware('role:chief|js|admin');
    Route::get('/benchmarks/summary', [S2sController::class, 'benchmarkSummary'])
        ->middleware('role:chief|js|admin');
});
