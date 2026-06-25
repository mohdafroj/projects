<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use App\Support\Metrics\Metrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsS2sStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_emits_qa_state_gauges_for_all_known_states(): void
    {
        $session = $this->makeSession();
        foreach ([
            ['seq' => 1, 'state' => 'passed', 'attempts' => 1],
            ['seq' => 2, 'state' => 'drift', 'attempts' => 2],
            ['seq' => 3, 'state' => 'corrected', 'attempts' => 1],
            ['seq' => 4, 'state' => 'failed', 'attempts' => 3],
            ['seq' => 5, 'state' => 'skipped', 'attempts' => 1],
        ] as $r) {
            $this->seedSegment($session, $r);
        }

        $body = (string) app(Metrics::class)->render(['s2s']);

        // All six known states must emit a sample, even if some are zero.
        // Prevents drift/SLO ratio queries going NaN on cold tables.
        foreach (['pending', 'passed', 'drift', 'corrected', 'failed', 'skipped'] as $state) {
            $this->assertMatchesRegularExpression(
                '/vani_s2s_segments_qa_state\{state="'.$state.'"\}\s+\d+/',
                $body,
                "missing qa_state gauge for state={$state}"
            );
            $this->assertMatchesRegularExpression(
                '/vani_s2s_recheck_attempts\{state="'.$state.'"\}\s+\d+/',
                $body,
                "missing recheck_attempts gauge for state={$state}"
            );
        }
    }

    public function test_qa_state_help_and_type_lines_are_emitted(): void
    {
        $session = $this->makeSession();
        $this->seedSegment($session, ['seq' => 1, 'state' => 'passed', 'attempts' => 1]);

        $body = (string) app(Metrics::class)->render(['s2s']);

        $this->assertStringContainsString('# HELP vani_s2s_segments_qa_state', $body);
        $this->assertStringContainsString('# TYPE vani_s2s_segments_qa_state gauge', $body);
        $this->assertStringContainsString('# HELP vani_s2s_recheck_attempts', $body);
        $this->assertStringContainsString('# TYPE vani_s2s_recheck_attempts gauge', $body);
    }

    public function test_metrics_endpoint_emits_recent_s2s_latency_and_stage_bottleneck_gauges(): void
    {
        config(['services.s2s.latency_health_window_minutes' => 30]);
        $session = $this->makeSession();
        foreach ([1200, 1800, 2400, 7000] as $idx => $latencyMs) {
            $this->seedSegment($session, [
                'seq' => $idx + 1,
                'state' => 'passed',
                'attempts' => 1,
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
        $old = $this->seedSegment($session, [
            'seq' => 99,
            'state' => 'passed',
            'attempts' => 1,
            'engine_meta' => [
                'dispatch' => [
                    'server_latency_ms' => 15000,
                    'response' => ['timings' => ['tts_ms' => 9000]],
                ],
            ],
        ]);
        $old->timestamps = false;
        $old->forceFill([
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ])->save();

        $body = (string) app(Metrics::class)->render(['s2s']);

        $this->assertStringContainsString('# HELP vani_s2s_latency_ms', $body);
        $this->assertStringContainsString('# TYPE vani_s2s_latency_ms gauge', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_latency_samples\{window="30m"\}\s+4/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_latency_ms\{quantile="p50",window="30m"\}\s+1800/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_latency_ms\{quantile="p95",window="30m"\}\s+7000/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_stage_latency_ms\{quantile="p95",stage="first_byte",window="30m"\}\s+450/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_stage_latency_ms\{quantile="p95",stage="stt",window="30m"\}\s+710/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_stage_latency_ms\{quantile="p95",stage="translation",window="30m"\}\s+970/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_stage_latency_ms\{quantile="p95",stage="tts",window="30m"\}\s+2700/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_latency_bottleneck\{stage="tts",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_latency_bottleneck\{stage="stt",window="30m"\}\s+0/', $body);
        $this->assertStringNotContainsString('15000', $body);
        $this->assertStringNotContainsString('9000', $body);
    }

    public function test_metrics_endpoint_emits_recent_s2s_client_error_gauges(): void
    {
        $audit = app(AuditLogger::class);
        $audit->log('s2s.client.error', null, [
            'kind' => 'fetch_5xx',
            'message' => 'S2S request failed with HTTP 500',
            'status' => 500,
            'language_code' => 'hi-IN',
        ]);
        $audit->log('s2s.client.error', null, [
            'kind' => 'window_error',
            'message' => 'Listener render failed',
        ]);
        $audit->log('s2s.client.error', null, [
            'kind' => 'audio_blocked',
            'message' => 'Browser blocked translated audio',
            'language_code' => 'bn-IN',
        ]);

        $body = (string) app(Metrics::class)->render(['s2s']);

        $this->assertStringContainsString('# HELP vani_s2s_client_errors_recent', $body);
        $this->assertStringContainsString('# TYPE vani_s2s_client_errors_recent gauge', $body);
        $this->assertStringContainsString('# HELP vani_s2s_client_errors_by_language_recent', $body);
        $this->assertStringContainsString('# TYPE vani_s2s_client_errors_by_language_recent gauge', $body);
        $this->assertStringContainsString('# HELP vani_s2s_client_error_threshold_breach', $body);
        $this->assertStringContainsString('# TYPE vani_s2s_client_error_threshold_breach gauge', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_total_recent\{window="30m"\}\s+3/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_recent\{kind="fetch_5xx",status_bucket="5xx",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_recent\{kind="window_error",status_bucket="none",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_recent\{kind="audio_blocked",status_bucket="none",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_by_language_recent\{kind="fetch_5xx",language_code="hi-in",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_by_language_recent\{kind="audio_blocked",language_code="bn-in",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_error_threshold_breach\{kind="none",language_code="none",threshold="5",type="total",window="30m"\}\s+0/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_error_threshold_breach\{kind="audio_blocked",language_code="none",threshold="3",type="live_kind",window="30m"\}\s+0/', $body);
        $this->assertStringNotContainsString('language_code="unknown"', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_recent\{kind="unhandledrejection",status_bucket="none",window="30m"\}\s+0/', $body);
        foreach (['language_error', 'audio_error', 'stream_error'] as $kind) {
            $this->assertMatchesRegularExpression(
                '/vani_s2s_client_errors_recent\{kind="'.$kind.'",status_bucket="none",window="30m"\}\s+0/',
                $body,
                "missing live streaming client-error baseline for {$kind}"
            );
        }
    }

    public function test_metrics_endpoint_emits_master_orchestrator_ready_status(): void
    {
        config([
            'services.s2s.latency_p95_watch_ms' => 3500,
            'services.s2s.latency_p95_degraded_ms' => 6000,
        ]);
        $session = $this->makeSession();
        foreach ([900, 1100, 1300] as $idx => $latencyMs) {
            $this->seedSegment($session, [
                'seq' => $idx + 1,
                'state' => 'passed',
                'attempts' => 1,
                'status' => 'processed',
                'engine_meta' => ['dispatch' => ['server_latency_ms' => $latencyMs]],
            ]);
        }

        $body = (string) app(Metrics::class)->render(['s2s']);

        $this->assertStringContainsString('# HELP vani_s2s_master_ready_for_live', $body);
        $this->assertStringContainsString('# TYPE vani_s2s_master_status gauge', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_ready_for_live\{window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_status\{status="up",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_status\{status="degraded",window="30m"\}\s+0/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_domain_status\{domain="performance",status="up",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_domain_status\{domain="stability",status="up",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_domain_status\{domain="transcript_audio",status="up",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_domain_status\{domain="storage",status="up",window="30m"\}\s+1/', $body);
    }

    public function test_metrics_endpoint_marks_master_orchestrator_degraded_from_live_domain_failures(): void
    {
        config([
            'services.s2s.latency_p95_watch_ms' => 3500,
            'services.s2s.latency_p95_degraded_ms' => 6000,
        ]);
        $session = $this->makeSession();
        $segment = $this->seedSegment($session, [
            'seq' => 1,
            'state' => 'failed',
            'attempts' => 1,
            'status' => 'degraded',
            'source_audio_path' => 's2s/devices/test/2026/05/28/10/chunk.wav',
            'engine_meta' => ['dispatch' => ['server_latency_ms' => 7000]],
        ]);
        S2sOutput::query()->create([
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'language_code' => 'hi-IN',
            'channel_name' => 'Hindi',
            'status' => 'provider_error',
        ]);

        $body = (string) app(Metrics::class)->render(['s2s']);

        $this->assertMatchesRegularExpression('/vani_s2s_master_ready_for_live\{window="30m"\}\s+0/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_status\{status="degraded",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_domain_status\{domain="performance",status="degraded",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_domain_status\{domain="stability",status="degraded",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_domain_status\{domain="transcript_audio",status="degraded",window="30m"\}\s+1/', $body);
    }

    public function test_metrics_master_stability_degrades_on_repeated_live_client_errors_for_one_language(): void
    {
        $audit = app(AuditLogger::class);
        foreach (range(1, 3) as $idx) {
            $audit->log('s2s.client.error', null, [
                'kind' => 'audio_blocked',
                'message' => 'Browser blocked Bangla translated audio '.$idx,
                'language_code' => 'bn-IN',
            ]);
        }

        $body = (string) app(Metrics::class)->render(['s2s']);

        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_total_recent\{window="30m"\}\s+3/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_by_language_recent\{kind="audio_blocked",language_code="bn-in",window="30m"\}\s+3/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_error_threshold_breach\{kind="audio_blocked",language_code="none",threshold="3",type="live_kind",window="30m"\}\s+3/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_error_threshold_breach\{kind="none",language_code="bn-in",threshold="3",type="language",window="30m"\}\s+3/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_domain_status\{domain="stability",status="degraded",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_ready_for_live\{window="30m"\}\s+0/', $body);
    }

    public function test_metrics_master_stability_uses_configured_live_client_error_thresholds(): void
    {
        config([
            'services.s2s.client_error_degraded_threshold' => 10,
            'services.s2s.client_live_kind_degraded_threshold' => 2,
            'services.s2s.client_language_degraded_threshold' => 2,
        ]);

        $audit = app(AuditLogger::class);
        foreach (range(1, 2) as $idx) {
            $audit->log('s2s.client.error', null, [
                'kind' => 'stream_error',
                'message' => 'Streaming output stalled '.$idx,
                'language_code' => 'ta-IN',
            ]);
        }

        $body = (string) app(Metrics::class)->render(['s2s']);

        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_total_recent\{window="30m"\}\s+2/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_errors_by_language_recent\{kind="stream_error",language_code="ta-in",window="30m"\}\s+2/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_error_threshold_breach\{kind="stream_error",language_code="none",threshold="2",type="live_kind",window="30m"\}\s+2/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_client_error_threshold_breach\{kind="none",language_code="ta-in",threshold="2",type="language",window="30m"\}\s+2/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_domain_status\{domain="stability",status="degraded",window="30m"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_master_ready_for_live\{window="30m"\}\s+0/', $body);
    }

    public function test_metrics_endpoint_emits_audio_archive_footprint_and_savings_gauges(): void
    {
        $session = $this->makeSession();
        $this->seedSegment($session, [
            'seq' => 1,
            'state' => 'passed',
            'attempts' => 1,
            'source_audio_path' => 's2s/devices/mic-1/2026/05/28/10/chunk-1.wav.gz',
            'engine_meta' => [
                'input_audio' => [
                    'size' => 10000,
                    'stored_size' => 2500,
                    'compression' => 'gzip',
                ],
            ],
        ]);
        $this->seedSegment($session, [
            'seq' => 2,
            'state' => 'passed',
            'attempts' => 1,
            'source_audio_path' => 's2s/devices/mic-1/2026/05/28/10/chunk-2.mp3',
            'engine_meta' => [
                'input_audio' => [
                    'size' => 4000,
                    'stored_size' => 4000,
                    'compression' => null,
                ],
            ],
        ]);
        $this->seedSegment($session, [
            'seq' => 3,
            'state' => 'passed',
            'attempts' => 1,
            'source_audio_path' => null,
            'engine_meta' => [
                'input_audio' => [
                    'size' => 8000,
                    'stored_size' => 2000,
                    'compression' => 'gzip',
                    'pruned_at' => now()->toISOString(),
                    'pruned_stored_size' => 2000,
                ],
            ],
        ]);
        $this->seedSegment($session, [
            'seq' => 4,
            'state' => 'passed',
            'attempts' => 1,
            'source_audio_path' => null,
            'engine_meta' => [
                'input_audio' => [
                    'compression' => 'gzip',
                    'pruned_reason' => 'retention_policy',
                    'pruned_original_path' => 's2s/devices/mic-1/2026/05/21/10/chunk-4.wav.gz',
                ],
            ],
        ]);

        $body = (string) app(Metrics::class)->render(['s2s']);

        $this->assertStringContainsString('# HELP vani_s2s_audio_archive_bytes', $body);
        $this->assertStringContainsString('# TYPE vani_s2s_audio_archive_segments gauge', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_segments\{state="cataloged",window="7d"\}\s+4/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_segments\{state="active",window="7d"\}\s+2/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_segments\{state="pruned",window="7d"\}\s+2/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_segments\{state="compressed",window="7d"\}\s+3/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_bytes\{kind="original",window="7d"\}\s+22000/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_bytes\{kind="stored",window="7d"\}\s+8500/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_bytes\{kind="saved",window="7d"\}\s+13500/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_bytes\{kind="pruned_released",window="7d"\}\s+2000/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_compression_segments\{compression="gzip",window="7d"\}\s+3/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_compression_segments\{compression="none",window="7d"\}\s+1/', $body);
        $this->assertMatchesRegularExpression('/vani_s2s_audio_archive_savings_ratio\{window="7d"\}\s+0\.613636/', $body);
    }

    private function makeSession(): S2sSession
    {
        return S2sSession::query()->create([
            'title' => 'Metrics Test',
            'mode' => 'upload',
            'input_source' => 'uploaded_file',
            'listener_scope' => 'outside_house',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'finished',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function seedSegment(S2sSession $session, array $r): S2sSegment
    {
        return S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => $r['seq'],
            'source_text' => 'text-'.$r['seq'],
            'qa_state' => $r['state'],
            'qa_attempts' => $r['attempts'],
            'qa_checked_at' => now(),
            'status' => $r['status'] ?? 'processed',
            'source_audio_path' => $r['source_audio_path'] ?? null,
            'engine_meta' => $r['engine_meta'] ?? [],
        ]);
    }
}
