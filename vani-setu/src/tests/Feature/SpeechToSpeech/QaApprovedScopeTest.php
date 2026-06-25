<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaApprovedScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_qa_approved_scope_filters_to_passed_and_corrected_only(): void
    {
        $session = $this->makeSession();
        $this->seedSegments($session, [
            ['seq' => 1, 'qa_state' => 'passed', 'text' => 'A'],
            ['seq' => 2, 'qa_state' => 'drift', 'text' => 'B'],
            ['seq' => 3, 'qa_state' => 'corrected', 'text' => 'C', 'corrected' => 'C-fixed'],
            ['seq' => 4, 'qa_state' => 'failed', 'text' => 'D'],
            ['seq' => 5, 'qa_state' => 'skipped', 'text' => 'E'],
            ['seq' => 6, 'qa_state' => 'pending', 'text' => 'F'],
        ]);

        $approved = S2sSegment::query()
            ->where('session_id', $session->id)
            ->qaApproved()
            ->orderBy('sequence_no')
            ->pluck('sequence_no')
            ->all();

        $this->assertSame([1, 3], $approved);
    }

    public function test_approved_transcript_returns_corrected_text_when_state_is_corrected(): void
    {
        $session = $this->makeSession();
        $segment = $this->seedSegments($session, [[
            'seq' => 1, 'qa_state' => 'corrected', 'text' => 'order in house', 'corrected' => 'Order, order in the House.',
        ]])->first();

        $this->assertSame('Order, order in the House.', $segment->approved_transcript);
    }

    public function test_approved_transcript_falls_back_to_source_text_for_passed(): void
    {
        $session = $this->makeSession();
        $segment = $this->seedSegments($session, [[
            'seq' => 1, 'qa_state' => 'passed', 'text' => 'budget discussion', 'corrected' => null,
        ]])->first();

        $this->assertSame('budget discussion', $segment->approved_transcript);
    }

    public function test_approved_transcript_falls_back_to_source_text_for_drift(): void
    {
        $session = $this->makeSession();
        $segment = $this->seedSegments($session, [[
            'seq' => 1, 'qa_state' => 'drift', 'text' => 'as-spoken', 'corrected' => 'reviewer-only suggestion',
        ]])->first();

        $this->assertSame('as-spoken', $segment->approved_transcript);
    }

    public function test_transcript_txt_export_uses_only_qa_approved_text_with_corrections(): void
    {
        $session = $this->makeSession();
        $this->seedSegments($session, [
            ['seq' => 1, 'qa_state' => 'passed', 'text' => 'Budget discussion', 'start_ms' => 0],
            ['seq' => 2, 'qa_state' => 'drift', 'text' => 'Do not export drift', 'start_ms' => 2000],
            ['seq' => 3, 'qa_state' => 'corrected', 'text' => 'order in house', 'corrected' => 'Order, order in the House.', 'start_ms' => 4000],
            ['seq' => 4, 'qa_state' => 'pending', 'text' => 'Do not export pending', 'start_ms' => 6000],
        ]);

        $response = $this->get("/speech-to-speech/sessions/{$session->id}/transcript.txt");
        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('QA approved segments: 2 / 4', $content);
        $this->assertStringContainsString('Omitted pending/unapproved segments: 2', $content);
        $this->assertStringContainsString('[0.00s · auto · qa:passed] Budget discussion', $content);
        $this->assertStringContainsString('[4.00s · auto · qa:corrected] Order, order in the House.', $content);
        $this->assertStringNotContainsString('Do not export drift', $content);
        $this->assertStringNotContainsString('Do not export pending', $content);
        $this->assertStringNotContainsString('order in house', $content);
    }

    public function test_transcript_srt_export_uses_only_qa_approved_text_with_corrections(): void
    {
        $session = $this->makeSession();
        $this->seedSegments($session, [
            ['seq' => 1, 'qa_state' => 'passed', 'text' => 'Budget discussion', 'start_ms' => 0, 'end_ms' => 1500],
            ['seq' => 2, 'qa_state' => 'failed', 'text' => 'Do not export failed', 'start_ms' => 1500, 'end_ms' => 2500],
            ['seq' => 3, 'qa_state' => 'corrected', 'text' => 'order in house', 'corrected' => 'Order, order in the House.', 'start_ms' => 2500, 'end_ms' => 4100],
        ]);

        $response = $this->get("/speech-to-speech/sessions/{$session->id}/transcript.srt");
        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString("1\n00:00:00,000 --> 00:00:01,500\nBudget discussion", $content);
        $this->assertStringContainsString("2\n00:00:02,500 --> 00:00:04,100\nOrder, order in the House.", $content);
        $this->assertStringNotContainsString('Do not export failed', $content);
        $this->assertStringNotContainsString('order in house', $content);
    }

    public function test_transcript_json_export_preserves_audio_links_and_qa_status_for_all_segments(): void
    {
        $session = $this->makeSession();
        [$passed, $corrected, $pending] = $this->seedSegments($session, [
            [
                'seq' => 1,
                'qa_state' => 'passed',
                'text' => 'Budget discussion',
                'start_ms' => 0,
                'end_ms' => 1500,
                'source_language' => 'en-IN',
                'audio_path' => 'device-a/2026-05-28/10/session/segment-1.wav.gz',
                'engine_meta' => [
                    'capture' => ['overlap_ms' => 500],
                    'input_audio' => [
                        'disk' => 's2s_input_audio',
                        'size' => 48000,
                        'stored_size' => 12000,
                        'compression' => 'gzip',
                        'mime_type' => 'audio/wav',
                    ],
                    'private_provider_context' => ['token' => 'do-not-export'],
                ],
            ],
            [
                'seq' => 2,
                'qa_state' => 'corrected',
                'text' => 'order in house',
                'corrected' => 'Order, order in the House.',
                'start_ms' => 1500,
                'end_ms' => 3100,
                'source_language' => 'en-IN',
                'audio_path' => 'device-a/2026-05-28/10/session/segment-2.wav.gz',
            ],
            [
                'seq' => 3,
                'qa_state' => 'pending',
                'text' => 'Needs review',
                'start_ms' => 3100,
                'end_ms' => 4700,
            ],
        ])->all();

        $output = $passed->outputs()->create([
            'session_id' => $session->id,
            'language_code' => 'hi-IN',
            'channel_name' => 'Hindi',
            'status' => 'ready',
            'text_output' => 'Budget discussion Hindi',
            'audio_output_path' => 'storage/s2s/hi-segment-1.wav',
            'output_meta' => ['audio_output_supported' => true, 'internal_latency' => 220],
        ]);
        $passed->outputs()->create([
            'session_id' => $session->id,
            'language_code' => 'ur-IN',
            'channel_name' => 'Urdu',
            'status' => 'provider_error',
            'text_output' => null,
            'audio_output_path' => null,
            'output_meta' => [
                'audio_output_supported' => true,
                'provider_payload' => ['message' => 'Provider returned no Urdu audio frame.'],
            ],
        ]);

        $response = $this->get("/speech-to-speech/sessions/{$session->id}/transcript.json");

        $response->assertOk()
            ->assertJsonPath('schema', 'vanisetu.s2s.transcript.v1')
            ->assertJsonPath('session.id', $session->id)
            ->assertJsonPath('qa_summary.approved_segments', 2)
            ->assertJsonPath('qa_summary.total_segments', 3)
            ->assertJsonPath('segments.0.approved_for_downstream', true)
            ->assertJsonPath('segments.0.source_audio.download_url', route('public.s2s.segments.audio', ['segment' => $passed->id]))
            ->assertJsonPath('segments.0.source_audio.compression', 'gzip')
            ->assertJsonPath('segments.0.timing.duration_ms', 1500)
            ->assertJsonPath('segments.0.timing.overlap_ms', 500)
            ->assertJsonPath('segments.0.edit_locator.session_id', $session->id)
            ->assertJsonPath('segments.0.edit_locator.segment_id', $passed->id)
            ->assertJsonPath('segments.0.edit_locator.sequence_no', 1)
            ->assertJsonPath('segments.0.edit_locator.start_ms', 0)
            ->assertJsonPath('segments.0.edit_locator.end_ms', 1500)
            ->assertJsonPath('segments.0.edit_locator.text_offset_start', 0)
            ->assertJsonPath('segments.0.edit_locator.text_offset_end', strlen('Budget discussion'))
            ->assertJsonPath('segments.0.edit_locator.source_audio_url', route('public.s2s.segments.audio', ['segment' => $passed->id]))
            ->assertJsonPath('segments.0.edit_locator.correction_url', route('public.s2s.segments.correction', ['segment' => $passed->id]))
            ->assertJsonPath('segments.0.edit_locator.replay_anchor', '#s2s-segment-'.$passed->id)
            ->assertJsonPath('segments.0.outputs.0.language_code', 'hi-IN')
            ->assertJsonPath('segments.0.outputs.0.output_locator.session_id', $session->id)
            ->assertJsonPath('segments.0.outputs.0.output_locator.segment_id', $passed->id)
            ->assertJsonPath('segments.0.outputs.0.output_locator.output_id', $output->id)
            ->assertJsonPath('segments.0.outputs.0.output_locator.language_code', 'hi-IN')
            ->assertJsonPath('segments.0.outputs.0.output_locator.start_ms', 0)
            ->assertJsonPath('segments.0.outputs.0.output_locator.end_ms', 1500)
            ->assertJsonPath('segments.0.outputs.0.output_locator.source_replay_anchor', '#s2s-segment-'.$passed->id)
            ->assertJsonPath('segments.0.outputs.0.output_locator.translated_audio_url', '/storage/s2s/hi-segment-1.wav')
            ->assertJsonPath('segments.0.outputs.0.output_locator.audio_resign_url', route('public.s2s.audio.resign', ['output' => $output->id]))
            ->assertJsonPath('segments.0.outputs.0.audio.url', '/storage/s2s/hi-segment-1.wav')
            ->assertJsonPath('segments.0.outputs.1.language_code', 'ur-IN')
            ->assertJsonPath('segments.0.outputs.1.status', 'provider_error')
            ->assertJsonPath('segments.0.outputs.1.error_message', 'Provider returned no Urdu audio frame.')
            ->assertJsonPath('segments.1.approved_transcript', 'Order, order in the House.')
            ->assertJsonPath('segments.1.qa.state', 'corrected')
            ->assertJsonPath('segments.1.edit_locator.text_offset_start', strlen('Budget discussion') + 1)
            ->assertJsonPath('segments.1.edit_locator.text_offset_end', strlen('Budget discussion') + 1 + strlen('Order, order in the House.'))
            ->assertJsonPath('segments.2.approved_for_downstream', false)
            ->assertJsonPath('segments.2.edit_locator.source_audio_url', null)
            ->assertJsonPath('segments.2.source_audio.download_url', null);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
        $json = $response->json();
        $this->assertArrayNotHasKey('engine_meta', $json['segments'][0]);
        $this->assertArrayNotHasKey('output_meta', $json['segments'][0]['outputs'][0]);
        $this->assertStringNotContainsString('audio_base64', $response->getContent());
        $this->assertStringNotContainsString('do-not-export', $response->getContent());
        $this->assertSame('Order, order in the House.', $corrected->approved_transcript);
        $this->assertSame('pending', $pending->qa_state);
    }

    private function makeSession(): S2sSession
    {
        return S2sSession::query()->create([
            'title' => 'Scope Test',
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

    /**
     * @param  array<int, array{seq:int, qa_state:string, text:?string, corrected?:?string, start_ms?:int, end_ms?:int, source_language?:?string, audio_path?:?string, engine_meta?:array<string, mixed>}>  $specs
     * @return \Illuminate\Support\Collection<int, S2sSegment>
     */
    private function seedSegments(S2sSession $session, array $specs): \Illuminate\Support\Collection
    {
        $segs = collect();
        foreach ($specs as $spec) {
            $segs->push(S2sSegment::query()->create([
                'session_id' => $session->id,
                'sequence_no' => $spec['seq'],
                'start_ms' => $spec['start_ms'] ?? 0,
                'end_ms' => $spec['end_ms'] ?? (($spec['start_ms'] ?? 0) + 2000),
                'source_language' => $spec['source_language'] ?? null,
                'source_text' => $spec['text'],
                'source_audio_path' => $spec['audio_path'] ?? null,
                'engine_meta' => $spec['engine_meta'] ?? [],
                'qa_state' => $spec['qa_state'],
                'qa_corrected_text' => $spec['corrected'] ?? null,
                'qa_checked_at' => now(),
            ]));
        }
        return $segs;
    }
}
