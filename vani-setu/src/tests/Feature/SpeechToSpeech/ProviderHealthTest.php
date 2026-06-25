<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Search\Models\StoredArtifact;
use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s2s_input_audio');
    }

    public function test_provider_health_returns_local_stability_when_ml_gateway_fails(): void
    {
        Http::fake([
            'http://ml-gateway:8000/v1/providers/health' => Http::response(['error' => 'unavailable'], 503),
        ]);
        $this->sourceAudioArtifact('active source audio', 's2s/devices/mic/2026-05-28/10/source.wav.gz', ['source-audio', 'compressed']);
        $this->sourceAudioArtifact('pruned source audio', null, ['source-audio', 'compressed', 'pruned']);

        $response = $this->getJson('/speech-to-speech/providers/health')
            ->assertOk()
            ->assertJsonPath('error', 'ml_gateway_503')
            ->assertJsonPath('providers.master_orchestrator.status', 'collecting')
            ->assertJsonPath('providers.master_orchestrator.ready_for_live', false)
            ->assertJsonPath('providers.master_orchestrator.domains.performance.status', 'collecting')
            ->assertJsonPath('providers.vani_setu_app.status', 'up')
            ->assertJsonPath('providers.s2s_db.status', 'up')
            ->assertJsonPath('providers.audio_archive.status', 'up')
            ->assertJsonPath('providers.audio_archive.cataloged_source_audio', 2)
            ->assertJsonPath('providers.audio_archive.active_cataloged_source_audio', 1)
            ->assertJsonPath('providers.audio_archive.pruned_cataloged_source_audio', 1)
            ->assertJsonPath('providers.master_orchestrator.domains.storage.signal', 'retention 30d · stale 0 · cataloged 2 · active 1 · pruned 1')
            ->assertJsonPath('providers.s2s_error_rate.status', 'up')
            ->assertJsonPath('providers.s2s_client_errors.status', 'up')
            ->assertJsonPath('providers.s2s_client_errors.recent_errors', 0)
            ->assertJsonPath('providers.s2s_latency_slo.status', 'collecting')
            ->assertJsonPath('providers.s2s_qa_recheck.status', 'up')
            ->assertJsonPath('application.vani_setu_app.status', 'up');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    private function sourceAudioArtifact(string $title, ?string $path, array $tags): StoredArtifact
    {
        return StoredArtifact::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'stored_disk' => 's2s_input_audio',
            'storage_path' => $path,
            'storage_uri' => $path ? 's2s_input_audio://'.$path : null,
            'mime_type' => 'audio/wav',
            'extension' => 'wav',
            'media_family' => 'archive',
            'source_module' => 'speech_to_speech',
            'size_bytes' => $path ? 2048 : 0,
            'tags' => $tags,
            'metadata' => $path ? [] : ['retention_pruned_at' => now()->toISOString()],
        ]);
    }

    public function test_provider_health_marks_recent_s2s_error_rate_degraded(): void
    {
        Http::fake([
            'http://ml-gateway:8000/v1/providers/health' => Http::response([
                'providers' => [
                    'sarvam_stt' => ['status' => 'up'],
                ],
            ], 200),
        ]);

        $session = S2sSession::query()->create([
            'title' => 'Health Probe',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $segment = S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'Health probe.',
            'status' => 'degraded',
        ]);

        S2sOutput::query()->create([
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'language_code' => 'hi-IN',
            'channel_name' => 'Hindi',
            'status' => 'provider_error',
        ]);

        $this->getJson('/speech-to-speech/providers/health')
            ->assertOk()
            ->assertJsonPath('providers.vani_setu_app.status', 'degraded')
            ->assertJsonPath('providers.s2s_error_rate.status', 'degraded')
            ->assertJsonPath('providers.s2s_error_rate.recent_segments', 1)
            ->assertJsonPath('providers.s2s_error_rate.degraded_segments', 1)
            ->assertJsonPath('providers.s2s_error_rate.provider_errors', 1)
            ->assertJsonPath('providers.master_orchestrator.status', 'degraded')
            ->assertJsonPath('providers.master_orchestrator.domains.stability.status', 'degraded')
            ->assertJsonPath('providers.master_orchestrator.ready_for_live', false)
            ->assertJsonPath('providers.sarvam_stt.status', 'up');
    }

    public function test_provider_health_marks_recent_client_errors_watch_and_degraded(): void
    {
        Http::fake([
            'http://ml-gateway:8000/v1/providers/health' => Http::response(['providers' => []], 200),
        ]);

        $audit = app(AuditLogger::class);
        $audit->log('s2s.client.error', null, [
            'kind' => 'window_error',
            'message' => 'Listener render failed',
            'url' => 'http://localhost/speech-to-speech/listener',
            'status' => null,
        ]);

        $this->getJson('/speech-to-speech/providers/health')
            ->assertOk()
            ->assertJsonPath('providers.s2s_client_errors.status', 'watch')
            ->assertJsonPath('providers.s2s_client_errors.recent_errors', 1)
            ->assertJsonPath('providers.s2s_client_errors.latest_error.kind', 'window_error')
            ->assertJsonPath('providers.s2s_client_errors.latest_error.message', 'Listener render failed');

        foreach (range(1, 4) as $idx) {
            $audit->log('s2s.client.error', null, [
                'kind' => 'fetch_5xx',
                'message' => 'S2S fetch failed '.$idx,
                'url' => 'http://localhost/speech-to-speech/sessions/1/status',
                'status' => 500,
                'language_code' => 'hi-IN',
                'chunk_id' => $idx,
            ]);
        }

        $this->getJson('/speech-to-speech/providers/health')
            ->assertOk()
            ->assertJsonPath('providers.vani_setu_app.status', 'degraded')
            ->assertJsonPath('providers.master_orchestrator.status', 'degraded')
            ->assertJsonPath('providers.master_orchestrator.domains.stability.status', 'degraded')
            ->assertJsonPath('providers.master_orchestrator.domains.stability.signal', 'server errors 0 · client errors 5 · breach total 5/5')
            ->assertJsonPath('providers.s2s_client_errors.status', 'degraded')
            ->assertJsonPath('providers.s2s_client_errors.recent_errors', 5)
            ->assertJsonPath('providers.s2s_client_errors.thresholds.total_degraded', 5)
            ->assertJsonPath('providers.s2s_client_errors.thresholds.live_kind_degraded', 3)
            ->assertJsonPath('providers.s2s_client_errors.thresholds.language_degraded', 3)
            ->assertJsonPath('providers.s2s_client_errors.threshold_breaches.0.type', 'total')
            ->assertJsonPath('providers.s2s_client_errors.threshold_breaches.0.count', 5)
            ->assertJsonPath('providers.s2s_client_errors.threshold_breaches.0.threshold', 5)
            ->assertJsonPath('providers.s2s_client_errors.threshold_breaches.1.type', 'language')
            ->assertJsonPath('providers.s2s_client_errors.threshold_breaches.1.language_code', 'hi-IN')
            ->assertJsonPath('providers.s2s_client_errors.breakdown_sampled', false)
            ->assertJsonPath('providers.s2s_client_errors.by_kind.0.kind', 'fetch_5xx')
            ->assertJsonPath('providers.s2s_client_errors.by_kind.0.count', 4)
            ->assertJsonPath('providers.s2s_client_errors.by_language.0.language_code', 'hi-IN')
            ->assertJsonPath('providers.s2s_client_errors.by_language.0.count', 4)
            ->assertJsonPath('providers.s2s_client_errors.latest_error.kind', 'fetch_5xx')
            ->assertJsonPath('providers.s2s_client_errors.latest_error.status', 500)
            ->assertJsonPath('providers.s2s_client_errors.latest_error.language_code', 'hi-IN')
            ->assertJsonPath('providers.s2s_client_errors.latest_error.chunk_id', 4);
    }

    public function test_provider_health_degrades_on_repeated_live_client_errors_for_one_language(): void
    {
        Http::fake([
            'http://ml-gateway:8000/v1/providers/health' => Http::response(['providers' => []], 200),
        ]);

        $audit = app(AuditLogger::class);
        foreach (range(1, 3) as $idx) {
            $audit->log('s2s.client.error', null, [
                'kind' => 'audio_blocked',
                'message' => 'Browser blocked Bangla translated audio '.$idx,
                'language_code' => 'bn-IN',
                'chunk_id' => $idx,
            ]);
        }

        $this->getJson('/speech-to-speech/providers/health')
            ->assertOk()
            ->assertJsonPath('providers.s2s_client_errors.status', 'degraded')
            ->assertJsonPath('providers.s2s_client_errors.recent_errors', 3)
            ->assertJsonPath('providers.s2s_client_errors.by_kind.0.kind', 'audio_blocked')
            ->assertJsonPath('providers.s2s_client_errors.by_kind.0.count', 3)
            ->assertJsonPath('providers.s2s_client_errors.by_language.0.language_code', 'bn-IN')
            ->assertJsonPath('providers.s2s_client_errors.by_language.0.count', 3)
            ->assertJsonPath('providers.s2s_client_errors.threshold_breaches.0.type', 'live_kind')
            ->assertJsonPath('providers.s2s_client_errors.threshold_breaches.0.kind', 'audio_blocked')
            ->assertJsonPath('providers.s2s_client_errors.threshold_breaches.1.type', 'language')
            ->assertJsonPath('providers.s2s_client_errors.threshold_breaches.1.language_code', 'bn-IN')
            ->assertJsonPath('providers.master_orchestrator.domains.stability.status', 'degraded')
            ->assertJsonPath('providers.master_orchestrator.domains.stability.signal', 'server errors 0 · client errors 3 · breach audio_blocked 3/3');
    }

    public function test_provider_health_uses_configured_live_client_error_thresholds(): void
    {
        config([
            'services.s2s.client_error_degraded_threshold' => 10,
            'services.s2s.client_live_kind_degraded_threshold' => 2,
            'services.s2s.client_language_degraded_threshold' => 2,
        ]);
        Http::fake([
            'http://ml-gateway:8000/v1/providers/health' => Http::response(['providers' => []], 200),
        ]);

        $audit = app(AuditLogger::class);
        foreach (range(1, 2) as $idx) {
            $audit->log('s2s.client.error', null, [
                'kind' => 'stream_error',
                'message' => 'Streaming output stalled '.$idx,
                'language_code' => 'ta-IN',
            ]);
        }

        $this->getJson('/speech-to-speech/providers/health')
            ->assertOk()
            ->assertJsonPath('providers.s2s_client_errors.status', 'degraded')
            ->assertJsonPath('providers.s2s_client_errors.recent_errors', 2)
            ->assertJsonPath('providers.s2s_client_errors.thresholds.total_degraded', 10)
            ->assertJsonPath('providers.s2s_client_errors.thresholds.live_kind_degraded', 2)
            ->assertJsonPath('providers.s2s_client_errors.thresholds.language_degraded', 2)
            ->assertJsonPath('providers.s2s_client_errors.by_kind.0.kind', 'stream_error')
            ->assertJsonPath('providers.s2s_client_errors.by_language.0.language_code', 'ta-IN')
            ->assertJsonPath('providers.master_orchestrator.domains.stability.status', 'degraded')
            ->assertJsonPath('providers.master_orchestrator.domains.stability.signal', 'server errors 0 · client errors 2 · breach stream_error 2/2');
    }

    public function test_provider_health_marks_qa_recheck_degraded_for_failed_or_stale_segments(): void
    {
        Http::fake([
            'http://ml-gateway:8000/v1/providers/health' => Http::response(['providers' => []], 200),
        ]);

        $session = S2sSession::query()->create([
            'title' => 'QA Health Probe',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'processing',
            'started_at' => now(),
        ]);

        S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 1,
            'source_language' => 'en-IN',
            'source_text' => 'Recheck failed.',
            'source_audio_path' => 's2s/qa/failed.wav',
            'status' => 'processed',
            'qa_state' => 'failed',
        ]);
        S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 2,
            'source_language' => 'en-IN',
            'source_text' => 'Recheck passed.',
            'source_audio_path' => 's2s/qa/passed.wav',
            'status' => 'processed',
            'qa_state' => 'passed',
        ]);

        $this->getJson('/speech-to-speech/providers/health')
            ->assertOk()
            ->assertJsonPath('providers.vani_setu_app.status', 'degraded')
            ->assertJsonPath('providers.s2s_qa_recheck.status', 'degraded')
            ->assertJsonPath('providers.s2s_qa_recheck.recent_segments', 2)
            ->assertJsonPath('providers.s2s_qa_recheck.failed_segments', 1)
            ->assertJsonPath('providers.s2s_qa_recheck.failure_rate', 0.5);
    }

    public function test_provider_health_marks_latency_slo_degraded_from_recent_p95(): void
    {
        config([
            'services.s2s.latency_health_window_minutes' => 30,
            'services.s2s.latency_p95_watch_ms' => 3500,
            'services.s2s.latency_p95_degraded_ms' => 6000,
        ]);
        Http::fake([
            'http://ml-gateway:8000/v1/providers/health' => Http::response(['providers' => []], 200),
        ]);

        $session = S2sSession::query()->create([
            'title' => 'Latency Health Probe',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'processing',
            'started_at' => now(),
        ]);

        foreach ([1200, 1800, 2400, 7000] as $idx => $latencyMs) {
            S2sSegment::query()->create([
                'session_id' => $session->id,
                'sequence_no' => $idx + 1,
                'source_language' => 'en-IN',
                'source_text' => 'Latency probe '.$idx,
                'status' => 'processed',
                'engine_meta' => [
                    'dispatch' => [
                        'server_latency_ms' => $latencyMs,
                        'response' => [
                            'timings' => [
                                'first_byte_ms' => 300 + ($idx * 50),
                                'stt_ms' => 500 + ($idx * 70),
                                'translation_ms' => 700 + ($idx * 90),
                                'tts_ms' => 1200 + ($idx * 500),
                            ],
                        ],
                    ],
                ],
            ]);
        }
        $oldSegment = S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 99,
            'source_language' => 'en-IN',
            'source_text' => 'Old slow sample should not count.',
            'status' => 'processed',
            'engine_meta' => [
                'dispatch' => ['server_latency_ms' => 15000],
            ],
        ]);
        $oldSegment->timestamps = false;
        $oldSegment->forceFill([
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ])->save();

        $this->getJson('/speech-to-speech/providers/health')
            ->assertOk()
            ->assertJsonPath('providers.vani_setu_app.status', 'degraded')
            ->assertJsonPath('providers.s2s_latency_slo.status', 'degraded')
            ->assertJsonPath('providers.s2s_latency_slo.samples', 4)
            ->assertJsonPath('providers.s2s_latency_slo.p50_ms', 1800)
            ->assertJsonPath('providers.s2s_latency_slo.p95_ms', 7000)
            ->assertJsonPath('providers.s2s_latency_slo.stage_latency_ms.first_byte.p95_ms', 450)
            ->assertJsonPath('providers.s2s_latency_slo.stage_latency_ms.stt.p95_ms', 710)
            ->assertJsonPath('providers.s2s_latency_slo.stage_latency_ms.translation.p95_ms', 970)
            ->assertJsonPath('providers.s2s_latency_slo.stage_latency_ms.tts.p95_ms', 2700)
            ->assertJsonPath('providers.s2s_latency_slo.bottleneck_stage', 'tts')
            ->assertJsonPath('providers.s2s_latency_slo.degraded_ms', 6000)
            ->assertJsonPath('providers.s2s_latency_slo.window_minutes', 30);
    }
}
