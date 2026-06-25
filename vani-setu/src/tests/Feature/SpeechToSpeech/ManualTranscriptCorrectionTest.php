<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Core\Models\AuditLog;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualTranscriptCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_public_console_can_save_segment_correction_without_breaking_audio_linkage(): void
    {
        $segment = $this->segment();

        $this->postJson("/speech-to-speech/segments/{$segment->id}/correction", [
            'corrected_text' => 'Order, order in the House.',
        ])->assertOk()
            ->assertJsonPath('segment.id', $segment->id)
            ->assertJsonPath('segment.qa_state', 'corrected')
            ->assertJsonPath('segment.approved_transcript', 'Order, order in the House.')
            ->assertJsonPath('segment.source_audio.segment_id', $segment->id)
            ->assertJsonPath('segment.source_audio.download_url', route('public.s2s.segments.audio', ['segment' => $segment->id]));

        $segment->refresh();
        $this->assertSame('uncorrected order in house', $segment->source_text);
        $this->assertSame('corrected', $segment->qa_state);
        $this->assertSame('Order, order in the House.', $segment->qa_corrected_text);
        $this->assertSame('Order, order in the House.', $segment->approved_transcript);

        $audit = AuditLog::query()->where('action', 's2s.transcript.corrected')->firstOrFail();
        $this->assertSame('speech_to_speech', $audit->chain_segment);
        $this->assertSame($segment->id, (int) $audit->subject_id);
        $this->assertSame($segment->sequence_no, $audit->payload['sequence_no']);
        $this->assertSame($segment->source_audio_path, $audit->payload['source_audio_path']);
    }

    public function test_corrected_segment_is_used_by_transcript_exports(): void
    {
        $segment = $this->segment();

        $this->postJson("/speech-to-speech/segments/{$segment->id}/correction", [
            'corrected_text' => 'Budget discussion corrected.',
        ])->assertOk();

        $content = $this->get("/speech-to-speech/sessions/{$segment->session_id}/transcript.txt")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('[0.00s · en-IN · qa:corrected] Budget discussion corrected.', $content);
        $this->assertStringNotContainsString('uncorrected order in house', $content);
    }

    private function segment(): S2sSegment
    {
        $session = S2sSession::query()->create([
            'title' => 'Manual Correction Session',
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

        return S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 2200,
            'source_language' => 'en-IN',
            'source_text' => 'uncorrected order in house',
            'source_audio_path' => 's2s/manual-correction/source.wav',
            'status' => 'processed',
            'qa_state' => 'pending',
            'engine_meta' => [
                'input_audio' => [
                    'disk' => 's2s_input_audio',
                    'path' => 's2s/manual-correction/source.wav',
                    'size' => 2048,
                ],
            ],
        ]);
    }
}
