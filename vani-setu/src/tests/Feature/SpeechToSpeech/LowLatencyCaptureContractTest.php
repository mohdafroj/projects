<?php

namespace Tests\Feature\SpeechToSpeech;

use Tests\TestCase;

class LowLatencyCaptureContractTest extends TestCase
{
    public function test_browser_capture_defaults_to_short_live_chunks(): void
    {
        $app = file_get_contents(public_path('vanisetu-speech-to-speech/app.jsx'));
        $recorder = file_get_contents(public_path('vanisetu-speech-to-speech/recorder.jsx'));

        $this->assertIsString($app);
        $this->assertIsString($recorder);
        $this->assertStringContainsString('const LIVE_CHUNK_DEFAULT_SECONDS = 3;', $app);
        $this->assertStringContainsString('const LIVE_CHUNK_MIN_SECONDS = 2;', $app);
        $this->assertStringContainsString('const LIVE_CHUNK_MAX_SECONDS = 12;', $app);
        $this->assertStringContainsString('const LIVE_CHUNK_STEP_SECONDS = 0.5;', $app);
        $this->assertStringContainsString('parseFloat(e.target.value)', $app);
        $this->assertStringContainsString('function useRecorder({ chunkSeconds = 3,', $recorder);
        $this->assertStringContainsString('const OVERLAP_MS = 500;', $recorder);
    }

    public function test_batched_status_polling_starts_fast_and_backs_off_tightly(): void
    {
        $sarvam = file_get_contents(public_path('vanisetu-speech-to-speech/sarvam.jsx'));

        $this->assertIsString($sarvam);
        $this->assertStringContainsString('async function pollS2SStatus({ chunkIdx, timeoutMs = 120000, intervalMs = 500, maxIntervalMs = 4000, signal })', $sarvam);
        $this->assertStringContainsString('status?sequence_no=${encodeURIComponent(chunkIdx)}', $sarvam);
        $this->assertStringContainsString('currentInterval = Math.min(currentInterval * 2, maxIntervalMs);', $sarvam);
        $this->assertStringContainsString('currentInterval = intervalMs;', $sarvam);
        $this->assertStringContainsString('start at 500ms so batched fallback does not add a hidden 2.5s', $sarvam);
        $this->assertStringContainsString('edit_locator: segment.edit_locator || null', $sarvam);
        $this->assertStringContainsString('replay_anchor: segment.edit_locator?.replay_anchor || null', $sarvam);
        $this->assertStringContainsString('correction_url: segment.edit_locator?.correction_url || null', $sarvam);
        $this->assertStringContainsString('const outputLocator = output.output_locator || null;', $sarvam);
        $this->assertStringContainsString('outputLocator, sendMs: serverMs ?? recvMs', $sarvam);
        $this->assertStringContainsString('outputLocator,', $sarvam);
        $this->assertStringContainsString('error: failed ? (output.error_message || "Provider audio/text generation failed") : null', $sarvam);
    }

    public function test_batched_status_polling_uses_chunk_abort_signal(): void
    {
        $sarvam = file_get_contents(public_path('vanisetu-speech-to-speech/sarvam.jsx'));

        $this->assertIsString($sarvam);
        $this->assertStringContainsString('async function getS2S(path, opts = {})', $sarvam);
        $this->assertStringContainsString('signal: opts.signal', $sarvam);
        $this->assertStringContainsString('function wait(ms, signal)', $sarvam);
        $this->assertStringContainsString('if (signal?.aborted) return Promise.reject(new DOMException("Aborted", "AbortError"));', $sarvam);
        $this->assertStringContainsString('signal.addEventListener("abort", abort, { once: true });', $sarvam);
        $this->assertStringContainsString('async function pollS2SStatus({ chunkIdx, timeoutMs = 120000, intervalMs = 500, maxIntervalMs = 4000, signal })', $sarvam);
        $this->assertStringContainsString('const payload = await getS2S(statusPath, { signal });', $sarvam);
        $this->assertStringContainsString('await wait(currentInterval, signal);', $sarvam);
        $this->assertStringContainsString('payload = await pollS2SStatus({ chunkIdx, signal });', $sarvam);
    }

    public function test_batched_status_poll_timeout_reports_to_client_health(): void
    {
        $sarvam = file_get_contents(public_path('vanisetu-speech-to-speech/sarvam.jsx'));

        $this->assertIsString($sarvam);
        $this->assertStringContainsString('window.reportS2SClientError("status_poll_timeout", {', $sarvam);
        $this->assertStringContainsString('source: "batched_status_poll"', $sarvam);
        $this->assertStringContainsString('chunk_id: chunkIdx', $sarvam);
        $this->assertStringContainsString('if (!signal?.aborted && typeof window.reportS2SClientError === "function")', $sarvam);
    }

    public function test_browser_keeps_translated_output_locator_in_language_stream_state(): void
    {
        $app = file_get_contents(public_path('vanisetu-speech-to-speech/app.jsx'));

        $this->assertIsString($app);
        $this->assertStringContainsString('outputLocator: p.outputLocator || null', $app);
        $this->assertStringContainsString('outputLocator: slot ? slot.outputLocator : null', $app);
    }

    public function test_browser_keeps_source_audio_pruned_metadata_for_transcript_panel(): void
    {
        $sarvam = file_get_contents(public_path('vanisetu-speech-to-speech/sarvam.jsx'));

        $this->assertIsString($sarvam);
        $this->assertStringContainsString('pruned: !!segment.source_audio?.pruned', $sarvam);
        $this->assertStringContainsString('pruned_at: segment.source_audio?.pruned_at || null', $sarvam);
        $this->assertStringContainsString('pruned_reason: segment.source_audio?.pruned_reason || null', $sarvam);
        $this->assertStringContainsString('pruned_stored_size: segment.source_audio?.pruned_stored_size || null', $sarvam);
    }

    public function test_streaming_audio_frames_get_live_output_locators(): void
    {
        $app = file_get_contents(public_path('vanisetu-speech-to-speech/app.jsx'));
        $sarvam = file_get_contents(public_path('vanisetu-speech-to-speech/sarvam.jsx'));
        $controller = file_get_contents(app_path('Modules/SpeechToSpeech/Controllers/S2sController.php'));

        $this->assertIsString($app);
        $this->assertIsString($sarvam);
        $this->assertIsString($controller);
        $this->assertStringContainsString("'session_id' => \$session->id", $controller);
        $this->assertStringContainsString('const streamMeta = {};', $sarvam);
        $this->assertStringContainsString('function streamedOutputLocator(streamMeta, lang, frameData = {})', $sarvam);
        $this->assertStringContainsString('audio_resign_url: audioKey ? `/speech-to-speech/audio-url?key=${encodeURIComponent(audioKey)}` : null', $sarvam);
        $this->assertStringContainsString('kind: "audio_locator"', $sarvam);
        $this->assertStringContainsString('outputLocator,', $sarvam);
        $this->assertStringContainsString('} else if (p.kind === "audio_locator") {', $app);
        $this->assertStringContainsString('audioKey: p.audioKey || existing.audioKey || null', $app);
        $this->assertStringContainsString('outputLocator: p.outputLocator || existing.outputLocator || null', $app);
    }

    public function test_live_output_streams_render_translated_text_and_locator_metadata(): void
    {
        $app = file_get_contents(public_path('vanisetu-speech-to-speech/app.jsx'));

        $this->assertIsString($app);
        $this->assertStringContainsString('function LiveOutputStreams({ streamsByLang, langLatency })', $app);
        $this->assertStringContainsString('<LiveOutputStreams streamsByLang={streamsByLang} langLatency={langLatency} />', $app);
        $this->assertStringContainsString('aria-label="Live translated output streams"', $app);
        $this->assertStringContainsString('data-output-segment-id={locator.segment_id || undefined}', $app);
        $this->assertStringContainsString('data-output-language={locator.language_code || stream.code}', $app);
        $this->assertStringContainsString('data-output-start={locator.start_ms ?? undefined}', $app);
        $this->assertStringContainsString('data-output-end={locator.end_ms ?? undefined}', $app);
        $this->assertStringContainsString('data-output-audio-resign-url={locator.audio_resign_url || undefined}', $app);
        $this->assertStringContainsString('data-output-source-anchor={locator.source_replay_anchor || undefined}', $app);
    }

    public function test_streaming_sentence_queue_uses_selected_output_device_and_volume(): void
    {
        $app = file_get_contents(public_path('vanisetu-speech-to-speech/app.jsx'));
        $sarvam = file_get_contents(public_path('vanisetu-speech-to-speech/sarvam.jsx'));

        $this->assertIsString($app);
        $this->assertIsString($sarvam);
        $this->assertStringContainsString('outputDeviceId,', $app);
        $this->assertStringContainsString('outputVolume,', $app);
        $this->assertStringContainsString('function createSentenceAudioQueue(onProgress, playbackOptions = {})', $sarvam);
        $this->assertStringContainsString('audioEl.volume = Math.max(0, Math.min(1, playbackOptions.outputVolume ?? 1));', $sarvam);
        $this->assertStringContainsString('if (playbackOptions.outputDeviceId && typeof audioEl.setSinkId === "function")', $sarvam);
        $this->assertStringContainsString('await audioEl.setSinkId(playbackOptions.outputDeviceId);', $sarvam);
        $this->assertStringContainsString('const q = createSentenceAudioQueue(onPartial || (() => {}), { outputDeviceId, outputVolume });', $sarvam);
    }

    public function test_streaming_playback_events_surface_in_live_output_stream(): void
    {
        $app = file_get_contents(public_path('vanisetu-speech-to-speech/app.jsx'));
        $sarvam = file_get_contents(public_path('vanisetu-speech-to-speech/sarvam.jsx'));

        $this->assertIsString($app);
        $this->assertIsString($sarvam);
        $this->assertStringContainsString('kind: "audio_start"', $sarvam);
        $this->assertStringContainsString('kind: "audio_blocked"', $sarvam);
        $this->assertStringContainsString('kind: "audio_end"', $sarvam);
        $this->assertStringContainsString('} else if (p.kind === "audio_start" || p.kind === "audio_end" || p.kind === "audio_blocked") {', $app);
        $this->assertStringContainsString('playbackState: p.kind === "audio_start" ? "playing" : (p.kind === "audio_end" ? "played" : "blocked")', $app);
        $this->assertStringContainsString('playbackError: p.kind === "audio_blocked" ? (p.error || "Browser blocked audio playback.") : null', $app);
        $this->assertStringContainsString('playbackState: slot ? slot.playbackState : null', $app);
        $this->assertStringContainsString('const playback = item.playbackState ? ` · ${item.playbackState}` : "";', $app);
        $this->assertStringContainsString('{item.playbackError && <span className="stream-line-error">{item.playbackError}</span>}', $app);
    }

    public function test_blocked_streaming_audio_reports_to_client_health(): void
    {
        $app = file_get_contents(public_path('vanisetu-speech-to-speech/app.jsx'));

        $this->assertIsString($app);
        $this->assertStringContainsString('if (p.kind === "audio_blocked" && typeof window.reportS2SClientError === "function")', $app);
        $this->assertStringContainsString('window.reportS2SClientError("audio_blocked", {', $app);
        $this->assertStringContainsString('source: "streaming_tts"', $app);
        $this->assertStringContainsString('chunk_id: id', $app);
        $this->assertStringContainsString('language_code: playbackLang', $app);
    }

    public function test_streaming_provider_error_frames_surface_in_ui_and_client_health(): void
    {
        $app = file_get_contents(public_path('vanisetu-speech-to-speech/app.jsx'));
        $sarvam = file_get_contents(public_path('vanisetu-speech-to-speech/sarvam.jsx'));

        $this->assertIsString($app);
        $this->assertIsString($sarvam);
        $this->assertStringContainsString('case "language_error":', $sarvam);
        $this->assertStringContainsString('case "audio_error":', $sarvam);
        $this->assertStringContainsString('case "stream_error":', $sarvam);
        $this->assertStringContainsString('} else if (p.kind === "language_error" || p.kind === "audio_error") {', $app);
        $this->assertStringContainsString('window.reportS2SClientError(p.kind, {', $app);
        $this->assertStringContainsString('perLang[errorLang] = {', $app);
        $this->assertStringContainsString('state: "error"', $app);
        $this->assertStringContainsString('} else if (p.kind === "stream_error") {', $app);
        $this->assertStringContainsString('window.reportS2SClientError("stream_error", {', $app);
        $this->assertStringContainsString('return { ...c, state: "error", error: message, perLang };', $app);
    }
}
