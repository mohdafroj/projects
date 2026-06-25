<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Core\Models\User;
use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BenchmarkSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_benchmark_summary_grades_vani_setu_from_existing_segments(): void
    {
        $session = $this->makeSession();
        $this->makeSegment($session, 1, 1200, 'processed', 'passed', true);
        $this->makeSegment($session, 2, 2200, 'processed', 'corrected', true);
        $prunedSegment = $this->makeSegment($session, 3, 4600, 'degraded', 'drift', false);
        $prunedSegment->forceFill([
            'source_audio_path' => null,
            'engine_meta' => array_merge($prunedSegment->engine_meta ?? [], [
                'input_audio' => [
                    'pruned_at' => now()->toISOString(),
                    'pruned_reason' => 'retention_policy',
                    'pruned_original_path' => 's2s/benchmark/session-'.$session->id.'/segment-3.wav',
                    'pruned_stored_size' => 2048,
                ],
            ]),
        ])->save();
        $this->assertFalse($prunedSegment->fresh()->hasActiveSourceAudio());
        $this->assertTrue($prunedSegment->fresh()->hasPrunedSourceAudioRecord());
        $this->assertTrue($prunedSegment->fresh()->hasSourceAudioLinkage());

        $response = $this->getJson('/speech-to-speech/benchmarks/summary')
            ->assertOk()
            ->assertJsonPath('window.basis', 'latest_s2s_segments')
            ->assertJsonPath('metrics.segments_sampled', 3)
            ->assertJsonPath('metrics.latency_samples', 3)
            ->assertJsonPath('metrics.p50_latency_ms', 2200)
            ->assertJsonPath('metrics.p95_latency_ms', 4600)
            ->assertJsonPath('metrics.stage_latency_ms.stt.p95_ms', 760)
            ->assertJsonPath('metrics.stage_latency_ms.translation.p95_ms', 1180)
            ->assertJsonPath('metrics.stage_latency_ms.tts.p95_ms', 1900)
            ->assertJsonPath('metrics.bottleneck_stage', 'tts')
            ->assertJsonPath('metrics.source_audio_linkage_rate', 1)
            ->assertJsonPath('metrics.source_audio_active_rate', 0.6667)
            ->assertJsonPath('metrics.source_audio_pruned_rate', 0.3333)
            ->assertJsonPath('metrics.qa_checked_segments', 3)
            ->assertJsonPath('metrics.qa_corrected_segments', 1)
            ->assertJsonPath('language_readiness.registry_languages', 23)
            ->assertJsonPath('language_readiness.scheduled_languages_required', 22)
            ->assertJsonPath('language_readiness.scheduled_languages_registered', 22)
            ->assertJsonPath('language_readiness.scheduled_coverage_rate', 1)
            ->assertJsonPath('language_readiness.scheduled_audio_output_languages', 10)
            ->assertJsonPath('language_readiness.audible_fallback_language', 'hi-IN')
            ->assertJsonPath('language_readiness.text_only_audio_fallback_ready', true)
            ->assertJsonPath('language_readiness.priority_outputs.en-IN.audio_output', true)
            ->assertJsonPath('language_readiness.priority_outputs.hi-IN.audio_output', true)
            ->assertJsonPath('language_readiness.status', 'translation_ready_audio_partial')
            ->assertJsonPath('rollout_agents.0.key', 'master_orchestrator')
            ->assertJsonPath('rollout_agents.1.key', 'latency_performance')
            ->assertJsonPath('rollout_agents.2.key', 'transcript_audio')
            ->assertJsonPath('rollout_agents.3.key', 'ui_stability')
            ->assertJsonPath('benchmark_comparison.best_reference_scores.latency', 90)
            ->assertJsonPath('benchmark_comparison.best_reference_scores.asr_quality', 88)
            ->assertJsonPath('benchmark_comparison.best_reference_scores.translation', 86)
            ->assertJsonPath('benchmark_comparison.best_reference_scores.tts', 90)
            ->assertJsonPath('benchmark_comparison.best_reference_average', 88)
            ->assertJsonPath('benchmark_comparison.measured_average', 73)
            ->assertJsonPath('benchmark_comparison.measured_gaps.latency', 59)
            ->assertJsonPath('benchmark_comparison.measured_gaps.asr_quality', 21)
            ->assertJsonPath('benchmark_comparison.measured_gaps.translation', -14)
            ->assertJsonPath('benchmark_comparison.measured_gaps.tts', 23)
            ->assertJsonPath('benchmark_comparison.measured_gaps.reliability', -12)
            ->assertJsonPath('systems.0.name', 'Vani Setu')
            ->assertJsonPath('systems.0.kind', 'measured');

        $systems = $response->json('systems');
        $agents = $response->json('rollout_agents');
        $this->assertSame('reference_target', $systems[1]['kind']);
        $this->assertArrayHasKey('latency', $systems[0]['scores']);
        $this->assertCount(4, $agents);
        $this->assertContains($agents[1]['status'], ['ready', 'watch', 'action', 'collecting']);
        $this->assertArrayHasKey('signal', $agents[2]);
        $this->assertStringContainsString('audio links', $agents[2]['signal']);
        $this->assertStringContainsString('active 67%', $agents[2]['signal']);
        $this->assertStringContainsString('pruned 33%', $agents[2]['signal']);
        $this->assertArrayHasKey('grade_bands', $response->json());
        $this->assertContains('as-IN', $response->json('language_readiness.text_only_scheduled_languages'));
        $this->assertContains('ur-IN', $response->json('language_readiness.text_only_scheduled_languages'));
    }

    public function test_api_benchmark_summary_is_available_to_chief_users(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'ADM-001')->firstOrFail());
        $this->makeSegment($this->makeSession(), 1, 900, 'processed', 'passed', true);

        $this->getJson('/api/s2s/benchmarks/summary')
            ->assertOk()
            ->assertJsonPath('systems.0.name', 'Vani Setu')
            ->assertJsonPath('metrics.p50_latency_ms', 900);
    }

    private function makeSession(): S2sSession
    {
        return S2sSession::query()->create([
            'title' => 'Benchmark Session',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'finished',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function makeSegment(S2sSession $session, int $sequence, int $latencyMs, string $status, string $qaState, bool $audio): S2sSegment
    {
        $segment = S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => $sequence,
            'start_ms' => ($sequence - 1) * 2000,
            'end_ms' => $sequence * 2000,
            'source_language' => 'en-IN',
            'source_text' => 'Segment '.$sequence,
            'source_audio_path' => 's2s/benchmark/session-'.$session->id.'/segment-'.$sequence.'.wav',
            'status' => $status,
            'engine_meta' => [
                'dispatch' => [
                    'server_latency_ms' => $latencyMs,
                    'response' => [
                        'timings' => [
                            'stt_ms' => 400 + ($sequence * 120),
                            'translation_ms' => 700 + ($sequence * 160),
                            'tts_ms' => 1000 + ($sequence * 300),
                            'first_byte_ms' => 450 + ($sequence * 90),
                        ],
                    ],
                ],
            ],
            'qa_state' => $qaState,
            'qa_checked_at' => now(),
        ]);

        S2sOutput::query()->create([
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'language_code' => 'hi-IN',
            'channel_name' => 'Hindi',
            'status' => 'completed',
            'text_output' => 'Output '.$sequence,
            'audio_output_path' => $audio ? '/storage/s2s/'.$session->id.'/'.$sequence.'.wav' : null,
        ]);

        return $segment;
    }
}
