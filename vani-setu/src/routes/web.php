<?php

use App\Http\Controllers\PublicDesignController;
use App\Http\Controllers\SpeechToSpeechPageController;
use App\Modules\SpeechToSpeech\Controllers\S2sController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicDesignController::class, 'home'])->name('public.home');
Route::get('/speech-to-speech', [SpeechToSpeechPageController::class, 'admin'])->name('public.s2s');
// Phase 4 — per-listener language channels (UN-booth). A listener opens this
// page, picks a session + their language, and gets the live translated output
// for that language over Reverb (text now; audio once MinIO is wired). Public,
// read-only — no auth, like tuning to an interpretation booth channel.
Route::get('/speech-to-speech/listen', function () {
    $langs = [
        ['code' => 'hi-IN', 'label' => 'हिन्दी · Hindi'],
        ['code' => 'en-IN', 'label' => 'English'],
        ['code' => 'ta-IN', 'label' => 'தமிழ் · Tamil'],
        ['code' => 'te-IN', 'label' => 'తెలుగు · Telugu'],
        ['code' => 'kn-IN', 'label' => 'ಕನ್ನಡ · Kannada'],
        ['code' => 'ml-IN', 'label' => 'മലയാളം · Malayalam'],
        ['code' => 'bn-IN', 'label' => 'বাংলা · Bengali'],
        ['code' => 'gu-IN', 'label' => 'ગુજરાતી · Gujarati'],
        ['code' => 'mr-IN', 'label' => 'मराठी · Marathi'],
        ['code' => 'pa-IN', 'label' => 'ਪੰਜਾਬੀ · Punjabi'],
        ['code' => 'od-IN', 'label' => 'ଓଡ଼ିଆ · Odia'],
    ];

    return view('s2s.listen', [
        'languages' => $langs,
        'reverbKey' => env('REVERB_APP_KEY'),
        'reverbHost' => env('REVERB_HOST', 'vanisetu.rajyasabha.digital'),
        'reverbPort' => env('REVERB_PORT', 443),
        'reverbScheme' => env('REVERB_SCHEME', 'https'),
    ]);
})->name('public.s2s.listen');
Route::get('/speech-to-text', [PublicDesignController::class, 'speechToText'])->name('public.s2t');
Route::post('/speech-to-text/captures', [PublicDesignController::class, 'storeSpeechToTextCapture'])->middleware('throttle:public-workflow-write')->name('public.s2t.captures.store');
Route::post('/speech-to-text/blocks/{block}', [PublicDesignController::class, 'updateSpeechToTextBlock'])->middleware('throttle:public-workflow-write')->name('public.s2t.blocks.update');
Route::post('/speech-to-text/slots/{slot}/handoff', [PublicDesignController::class, 'handoffSpeechToTextSlot'])->middleware('throttle:public-workflow-write')->name('public.s2t.slots.handoff');
Route::get('/text-to-text', [PublicDesignController::class, 'textToText'])->name('public.t2t');
Route::post('/text-to-text/assignments', [PublicDesignController::class, 'storeTextToTextAssignment'])->middleware('throttle:public-workflow-write')->name('public.t2t.assignments.store');
Route::post('/text-to-text/assignments/{assignment}/translate', [PublicDesignController::class, 'translateTextToTextAssignment'])->middleware('throttle:public-workflow-write')->name('public.t2t.assignments.translate');
Route::post('/text-to-text/glossary', [PublicDesignController::class, 'storeTextToTextGlossary'])->middleware('throttle:public-workflow-write')->name('public.t2t.glossary.store');
Route::get('/design-standard', [PublicDesignController::class, 'standard'])->name('public.standard');

Route::post('/speech-to-speech/sessions', [SpeechToSpeechPageController::class, 'storeSession'])->middleware('throttle:public-workflow-write')->name('public.s2s.sessions.store');
Route::post('/speech-to-speech/sessions/{session}/segments', [SpeechToSpeechPageController::class, 'storeSegment'])->middleware('throttle:public-workflow-write')->name('public.s2s.segments.store');
// Streaming SSE counterpart of /segments — the JS client posts here when
// S2S_STREAMING_TTS is on so the first synthesised sentence can be played
// while later ones are still in flight. Without this route the browser hit
// Laravel's 404 page, fell back to the batched /segments route, and the
// circuit-broken translate path returned no audio at all (silent output).
Route::post('/speech-to-speech/sessions/{session}/segments/stream', [S2sController::class, 'streamSegment'])->middleware('throttle:public-workflow-write')->name('public.s2s.segments.stream');
Route::post('/speech-to-speech/sessions/{session}/targets', [SpeechToSpeechPageController::class, 'updateSessionTargets'])->middleware('throttle:public-workflow-write')->name('public.s2s.sessions.targets');
Route::post('/speech-to-speech/sessions/{session}/finish', [SpeechToSpeechPageController::class, 'finishSession'])->middleware('throttle:public-workflow-write')->name('public.s2s.sessions.finish');
Route::get('/speech-to-speech/sessions/{session}/status', [SpeechToSpeechPageController::class, 'status'])->name('public.s2s.sessions.status');
Route::post('/speech-to-speech/client-errors', [SpeechToSpeechPageController::class, 'reportClientError'])->middleware('throttle:public-workflow-write')->name('public.s2s.client-errors');
Route::get('/speech-to-speech/providers/health', [SpeechToSpeechPageController::class, 'providersHealth'])->name('public.s2s.providers.health');
Route::get('/speech-to-speech/benchmarks/summary', [SpeechToSpeechPageController::class, 'benchmarkSummary'])->name('public.s2s.benchmarks.summary');
Route::get('/speech-to-speech/sessions/{session}/transcript.txt', [SpeechToSpeechPageController::class, 'exportTranscriptTxt'])->name('public.s2s.sessions.transcript.txt');
Route::get('/speech-to-speech/sessions/{session}/transcript.srt', [SpeechToSpeechPageController::class, 'exportTranscriptSrt'])->name('public.s2s.sessions.transcript.srt');
Route::get('/speech-to-speech/sessions/{session}/transcript.json', [SpeechToSpeechPageController::class, 'exportTranscriptJson'])->name('public.s2s.sessions.transcript.json');
// PDF / DOCX / ODT export from the live transcript built client-side. The
// JSX TranscriptPanel POSTs the assembled entries as JSON; the controller
// renders the requested format and returns it as an attachment.
Route::post('/speech-to-speech/export', [SpeechToSpeechPageController::class, 'exportTranscriptDocument'])
    ->middleware('throttle:public-workflow-write')
    ->name('public.s2s.transcript.export');
Route::get('/speech-to-speech/segments/{segment}/audio', [SpeechToSpeechPageController::class, 'downloadSegmentAudio'])->name('public.s2s.segments.audio');
Route::post('/speech-to-speech/segments/{segment}/correction', [SpeechToSpeechPageController::class, 'correctSegmentTranscript'])->middleware('throttle:public-workflow-write')->name('public.s2s.segments.correction');
// Iter-14: re-sign endpoint for MinIO audio URLs whose 15-min TTL has lapsed.
// Browser polls this when a paused session's stored URL is within 60s of
// expiry; Laravel proxies to ml-gateway /v1/audio/re-sign with service auth.
Route::get('/speech-to-speech/outputs/{output}/audio-url', [SpeechToSpeechPageController::class, 'reSignAudio'])->name('public.s2s.audio.resign');
// Iter-15: SSE-streamed sentences have no s2s_outputs row, so the
// output-id-keyed re-sign route above can't refresh them. This sibling
// route accepts the raw MinIO object key (emitted in each SSE audio frame
// as ``audio_key``) and forwards to the same ml-gateway /v1/audio/re-sign
// endpoint with service auth.
Route::get('/speech-to-speech/audio-url', [SpeechToSpeechPageController::class, 'reSignAudioByKey'])->name('public.s2s.audio.resign.key');
Route::get('/speech-to-speech/glossary', [SpeechToSpeechPageController::class, 'glossaryIndex'])->name('public.s2s.glossary.index');
Route::post('/speech-to-speech/glossary', [SpeechToSpeechPageController::class, 'glossaryUpsert'])->middleware('throttle:public-workflow-write')->name('public.s2s.glossary.upsert');
Route::delete('/speech-to-speech/glossary/{entry}', [SpeechToSpeechPageController::class, 'glossaryDelete'])->middleware('throttle:public-workflow-write')->name('public.s2s.glossary.delete');
Route::post('/speech-to-speech/glossary/import', [SpeechToSpeechPageController::class, 'glossaryImport'])->middleware('throttle:public-workflow-write')->name('public.s2s.glossary.import');
Route::get('/speech-to-speech/glossary/export', [SpeechToSpeechPageController::class, 'glossaryExport'])->name('public.s2s.glossary.export');
Route::post('/speech-to-speech/glossary/seed-members', [SpeechToSpeechPageController::class, 'glossarySeedMembers'])->middleware('throttle:public-workflow-write')->name('public.s2s.glossary.seed-members');
Route::get('/speech-to-speech/vocabulary', [SpeechToSpeechPageController::class, 'vocabularyIndex'])->name('public.s2s.vocabulary.index');
Route::post('/speech-to-speech/vocabulary', [SpeechToSpeechPageController::class, 'storeVocabulary'])->middleware('throttle:public-workflow-write')->name('public.s2s.vocabulary.store');
Route::put('/speech-to-speech/vocabulary/{rule}', [SpeechToSpeechPageController::class, 'updateVocabulary'])->middleware('throttle:public-workflow-write')->name('public.s2s.vocabulary.update');
Route::get('/speech-to-speech/listener', [SpeechToSpeechPageController::class, 'listener'])->name('public.s2s.listener');
Route::get('/speech-to-speech/admin', [SpeechToSpeechPageController::class, 'admin'])->name('public.s2s.admin');
Route::post('/speech-to-speech/runtime', [SpeechToSpeechPageController::class, 'updateRuntime'])->middleware('throttle:public-workflow-write')->name('public.s2s.runtime.update');

Route::redirect('/login', '/app/login')->name('login');
Route::view('/app/login', 'app.workspace')->name('app.login');
Route::view('/app/reporter', 'app.workspace')->name('app.reporter');
Route::view('/app/reporter/slots/{slot}', 'app.workspace')->name('app.reporter.slots');
Route::view('/app/translator', 'app.workspace')->name('app.translator');
Route::view('/app/translator/assignments/{assignment}', 'app.workspace')->name('app.translator.assignments');
Route::view('/app/reviewer', 'app.workspace')->name('app.reviewer');
Route::view('/app/reviewer/assignments/{assignment}', 'app.workspace')->name('app.reviewer.assignments');
Route::view('/app/sg', 'app.workspace')->name('app.sg');
Route::view('/app/sg/windows/{window}', 'app.workspace')->name('app.sg.windows');
Route::view('/app/director', 'app.workspace')->name('app.director');
Route::view('/app/director/jobs/{job}', 'app.workspace')->name('app.director.jobs');
Route::view('/app/synopsis', 'app.workspace')->name('app.synopsis');
Route::view('/app/synopsis/chunks/{consolidation}', 'app.workspace')->name('app.synopsis.chunks');
