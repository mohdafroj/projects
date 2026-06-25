<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// S2S recheck-engine self-healing pass. Re-dispatches segments stuck
// in qa_state='failed' once enough cool-down has elapsed for upstream
// to plausibly have recovered. Cool-down + retry-cap come from
// services.s2s_recheck.retry_* so behaviour is config-driven. Skipped
// when the recheck transcriber is the Null driver (no point retrying
// what will fail the same way).
Schedule::command('s2s:recheck-retry-failed')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->skip(fn () => (string) config('services.s2s_recheck.transcriber', 'null') === 'null')
    ->name('s2s.recheck.retry-failed');

// Low-footprint archive lifecycle: source audio is retained for QA/replay
// while a sitting is fresh, then pruned after the configured finished-session
// retention window. Transcripts, QA metadata, and segment timing remain.
Schedule::command('s2s:prune-audio-archive')
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->skip(fn () => (int) config('services.s2s.audio_archive_retention_days', 30) <= 0)
    ->name('s2s.audio-archive.prune');
