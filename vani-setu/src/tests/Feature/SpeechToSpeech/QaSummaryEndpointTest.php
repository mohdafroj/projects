<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Core\Models\User;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QaSummaryEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_qa_summary_returns_verdict_distribution_for_admin(): void
    {
        $session = $this->seedSessionWithVerdicts();
        $pruned = S2sSegment::query()
            ->where('session_id', $session->id)
            ->where('sequence_no', 4)
            ->firstOrFail();
        $this->assertFalse($pruned->hasActiveSourceAudio());
        $this->assertTrue($pruned->hasPrunedSourceAudioRecord());
        $this->assertTrue($pruned->hasSourceAudioLinkage());

        Sanctum::actingAs(User::query()->where('employee_id', 'ADM-001')->firstOrFail());

        $this->getJson("/api/s2s/sessions/{$session->id}/qa-summary")
            ->assertOk()
            ->assertJsonPath('session_id', $session->id)
            ->assertJsonPath('total_segments', 4)
            ->assertJsonPath('audio_segments', 4)
            ->assertJsonPath('active_audio_segments', 3)
            ->assertJsonPath('pruned_audio_segments', 1)
            ->assertJsonStructure([
                'session_id',
                'total_segments',
                'audio_segments',
                'active_audio_segments',
                'pruned_audio_segments',
                'verdicts' => [['state', 'count']],
                'sample_drift',
                'sample_corrected',
                'last_checked_at',
            ]);

        $response = $this->getJson("/api/s2s/sessions/{$session->id}/qa-summary");
        $verdicts = collect($response->json('verdicts'))->keyBy('state');
        $this->assertSame(1, $verdicts->get('passed')['count']);
        $this->assertSame(1, $verdicts->get('drift')['count']);
        $this->assertSame(1, $verdicts->get('corrected')['count']);
    }

    public function test_qa_summary_rejects_non_admin(): void
    {
        $session = $this->seedSessionWithVerdicts();
        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $this->getJson("/api/s2s/sessions/{$session->id}/qa-summary")
            ->assertForbidden();
    }

    public function test_qa_summary_surfaces_sample_drift_with_second_pass_text(): void
    {
        $session = $this->seedSessionWithVerdicts();
        Sanctum::actingAs(User::query()->where('employee_id', 'ADM-001')->firstOrFail());

        $payload = $this->getJson("/api/s2s/sessions/{$session->id}/qa-summary")->json();
        $samples = collect($payload['sample_drift'])->keyBy('sequence_no');
        $this->assertTrue($samples->has(2));
        $this->assertSame('budget discussion is underway', $samples->get(2)['source_text']);
        $this->assertSame('audit discussion is delayed', $samples->get(2)['second_pass']);
    }

    private function seedSessionWithVerdicts(): S2sSession
    {
        $session = S2sSession::query()->create([
            'title' => 'QA Summary Test',
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

        S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 1,
            'source_text' => 'budget discussion underway',
            'source_audio_path' => 's2s/1/segments/1.wav',
            'qa_state' => 'passed',
            'qa_score' => 1.0,
            'qa_checked_at' => now(),
        ]);
        S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 2,
            'source_text' => 'budget discussion is underway',
            'source_audio_path' => 's2s/1/segments/2.wav',
            'qa_state' => 'drift',
            'qa_score' => 0.30,
            'qa_engine_meta' => [
                'second_pass' => ['text' => 'audit discussion is delayed'],
                'wer' => 0.7,
            ],
            'qa_checked_at' => now(),
        ]);
        S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 3,
            'source_text' => 'order in the house',
            'source_audio_path' => 's2s/1/segments/3.wav',
            'qa_state' => 'corrected',
            'qa_score' => 0.85,
            'qa_corrected_text' => 'Order, order in the House.',
            'qa_checked_at' => now(),
        ]);
        S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 4,
            'source_text' => null,
            'source_audio_path' => null,
            'engine_meta' => [
                'input_audio' => [
                    'pruned_at' => now()->toISOString(),
                    'pruned_reason' => 'retention_policy',
                    'pruned_original_path' => 's2s/1/segments/4.wav.gz',
                    'pruned_stored_size' => 4096,
                ],
            ],
            'qa_state' => 'skipped',
            'qa_checked_at' => now(),
        ]);

        return $session->refresh();
    }
}
