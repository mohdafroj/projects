<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The /speech-to-speech/sessions/{id}/status endpoint is polled by
 * sarvam.jsx::pollS2SStatus on the batched fallback path and is the dominant
 * status-traffic source per active S2S session. This test pins the slim
 * payload shape so future edits to sessionPayload() don't accidentally
 * re-introduce the duplicated session-level outputs[] or the engine_meta
 * blob (which alone added tens of KB per response on real sessions).
 */
class StatusPayloadShapeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // refreshPendingOutputs() hits ml-gateway. Stub it so the test
        // doesn't try to dial a real network endpoint.
        Http::fake([
            'http://ml-gateway:8000/*' => Http::response([], 200),
        ]);
    }

    public function test_status_route_returns_slim_payload_omitting_heavy_fields(): void
    {
        $session = S2sSession::query()->create([
            'title' => 'Status Shape Probe',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'audio_input_meta' => ['hidden_blob' => str_repeat('A', 2048)],
            'archive_meta' => ['segments' => 1, 'extra_blob' => str_repeat('B', 2048)],
            'fallback_meta' => ['chain' => [['provider' => 'sarvam', 'role' => 'primary']]],
            'engine_meta' => ['tts_speaker' => 'meera', 'leak_canary' => str_repeat('C', 2048)],
            'announcement_text' => 'AI translated voice.',
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $segment = S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 4000,
            'source_language' => 'en-IN',
            'source_text' => 'Session opens at 10 am.',
            'source_audio_path' => 's2s/'.$session->id.'/segments/1/audio.wav',
            'status' => 'processed',
            'qa_state' => 'corrected',
            'qa_corrected_text' => 'Session opens at ten am.',
            'translated_segments' => [],
            'engine_meta' => [
                'dispatch' => ['server_latency_ms' => 412, 'status' => 200],
                'input_audio' => ['disk' => 'vani_audio', 'size' => 8192, 'stored_size' => 4096, 'compression' => 'gzip'],
                'capture' => ['overlap_ms' => 250],
                'huge_debug_blob' => str_repeat('D', 4096),
            ],
        ]);

        $output = S2sOutput::query()->create([
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'language_code' => 'hi-IN',
            'channel_name' => 'hi-IN-primary',
            'status' => 'completed',
            'text_output' => 'सत्र सुबह 10 बजे शुरू होगा।',
            'audio_output_path' => '/storage/s2s/'.$session->id.'/seg-1-hi.wav',
            'output_meta' => [
                'audio_output_supported' => true,
                'provider_used' => 'sarvam',
                'leak_canary' => str_repeat('E', 2048),
            ],
        ]);
        S2sOutput::query()->create([
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'language_code' => 'ur-IN',
            'channel_name' => 'ur-IN-primary',
            'status' => 'provider_error',
            'text_output' => null,
            'audio_output_path' => null,
            'output_meta' => [
                'audio_output_supported' => true,
                'provider_payload' => ['error' => 'Sarvam streaming audio failed for Urdu output.'],
                'provider_response' => ['huge_debug_blob' => str_repeat('F', 2048)],
            ],
        ]);

        $response = $this->getJson("/speech-to-speech/sessions/{$session->id}/status")->assertOk();

        // Fields the React poller actually reads MUST be present.
        $response
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('mode', 'live')
            ->assertJsonPath('source_lang', 'en-IN')
            ->assertJsonPath('target_lang', 'hi-IN')
            ->assertJsonPath('available_target_langs.0', 'hi-IN')
            ->assertJsonPath('archive_meta.segments', 1)
            ->assertJsonPath('segments.0.id', $segment->id)
            ->assertJsonPath('segments.0.sequence_no', 1)
            ->assertJsonPath('segments.0.status', 'processed')
            ->assertJsonPath('segments.0.start_ms', 0)
            ->assertJsonPath('segments.0.end_ms', 4000)
            ->assertJsonPath('segments.0.timing.duration_ms', 4000)
            ->assertJsonPath('segments.0.timing.overlap_ms', 250)
            ->assertJsonPath('segments.0.edit_locator.session_id', $session->id)
            ->assertJsonPath('segments.0.edit_locator.segment_id', $segment->id)
            ->assertJsonPath('segments.0.edit_locator.sequence_no', 1)
            ->assertJsonPath('segments.0.edit_locator.start_ms', 0)
            ->assertJsonPath('segments.0.edit_locator.end_ms', 4000)
            ->assertJsonPath('segments.0.edit_locator.source_audio_url', route('public.s2s.segments.audio', ['segment' => $segment->id]))
            ->assertJsonPath('segments.0.edit_locator.correction_url', route('public.s2s.segments.correction', ['segment' => $segment->id]))
            ->assertJsonPath('segments.0.edit_locator.replay_anchor', '#s2s-segment-'.$segment->id)
            ->assertJsonPath('segments.0.source_language', 'en-IN')
            ->assertJsonPath('segments.0.source_text', 'Session opens at 10 am.')
            ->assertJsonPath('segments.0.qa_state', 'corrected')
            ->assertJsonPath('segments.0.qa_corrected_text', 'Session opens at ten am.')
            ->assertJsonPath('segments.0.approved_transcript', 'Session opens at ten am.')
            ->assertJsonPath('segments.0.latency_ms', 412)
            ->assertJsonPath('segments.0.source_audio.segment_id', $segment->id)
            ->assertJsonPath('segments.0.source_audio.disk', 'vani_audio')
            ->assertJsonPath('segments.0.source_audio.path', 's2s/'.$session->id.'/segments/1/audio.wav')
            ->assertJsonPath('segments.0.source_audio.stored_size', 4096)
            ->assertJsonPath('segments.0.source_audio.compression', 'gzip')
            ->assertJsonPath('segments.0.source_audio.pruned', false)
            ->assertJsonPath('segments.0.source_audio.pruned_at', null)
            ->assertJsonPath('segments.0.source_audio.download_url', route('public.s2s.segments.audio', ['segment' => $segment->id]))
            ->assertJsonPath('segments.0.outputs.0.language_code', 'hi-IN')
            ->assertJsonPath('segments.0.outputs.0.status', 'completed')
            ->assertJsonPath('segments.0.outputs.0.text_output', 'सत्र सुबह 10 बजे शुरू होगा।')
            ->assertJsonPath('segments.0.outputs.0.error_message', null)
            ->assertJsonPath('segments.0.outputs.0.audio_output_path', '/storage/s2s/'.$session->id.'/seg-1-hi.wav')
            ->assertJsonPath('segments.0.outputs.1.language_code', 'ur-IN')
            ->assertJsonPath('segments.0.outputs.1.status', 'provider_error')
            ->assertJsonPath('segments.0.outputs.1.error_message', 'Sarvam streaming audio failed for Urdu output.')
            ->assertJsonPath('segments.0.outputs.0.output_locator.session_id', $session->id)
            ->assertJsonPath('segments.0.outputs.0.output_locator.segment_id', $segment->id)
            ->assertJsonPath('segments.0.outputs.0.output_locator.output_id', $output->id)
            ->assertJsonPath('segments.0.outputs.0.output_locator.language_code', 'hi-IN')
            ->assertJsonPath('segments.0.outputs.0.output_locator.start_ms', 0)
            ->assertJsonPath('segments.0.outputs.0.output_locator.end_ms', 4000)
            ->assertJsonPath('segments.0.outputs.0.output_locator.translated_audio_url', '/storage/s2s/'.$session->id.'/seg-1-hi.wav')
            ->assertJsonPath('segments.0.outputs.0.output_locator.audio_resign_url', route('public.s2s.audio.resign', ['output' => $output->id]))
            ->assertJsonPath('segments.0.outputs.0.output_locator.source_replay_anchor', '#s2s-segment-'.$segment->id);

        $payload = $response->json();

        // Slim contract: top-level outputs[] (duplicated from segments.*.outputs)
        // must NOT be present — biggest size win on real sessions.
        $this->assertArrayNotHasKey('outputs', $payload, 'Top-level outputs[] must be omitted from status payload');

        // Heavy meta blobs must NOT leak via status.
        $this->assertArrayNotHasKey('engine_meta', $payload);
        $this->assertArrayNotHasKey('audio_input_meta', $payload);
        $this->assertArrayNotHasKey('fallback_meta', $payload);
        $this->assertArrayNotHasKey('announcement_text', $payload);
        $this->assertStringNotContainsString('leak_canary', json_encode($payload));
        $this->assertStringNotContainsString('huge_debug_blob', json_encode($payload));

        // Per-segment slim — engine_meta must NOT leak.
        $segmentPayload = $payload['segments'][0];
        $this->assertArrayNotHasKey('engine_meta', $segmentPayload);
        $this->assertArrayNotHasKey('dispatch_status', $segmentPayload);

        // Per-output slim — channel_name / audio_output_supported / output_meta
        // NOT consumed by the poller and must NOT be emitted. ``id`` IS emitted
        // as of iter-14 so the JSX audio queue can hit the
        // /speech-to-speech/outputs/{id}/audio-url re-sign endpoint when the
        // stored MinIO URL is about to TTL out on a long-paused session.
        $outputPayload = $segmentPayload['outputs'][0];
        $this->assertArrayNotHasKey('channel_name', $outputPayload);
        $this->assertArrayNotHasKey('audio_output_supported', $outputPayload);
        $this->assertArrayNotHasKey('output_meta', $outputPayload);
        $this->assertArrayNotHasKey('latency_ms', $outputPayload);
        $this->assertArrayHasKey('id', $outputPayload);
        $this->assertIsInt($outputPayload['id']);

        // audio_base64 (used by some upload paths via inlineAudioPayload) must
        // never appear under any output — the poll path should reference the
        // storage URL form only.
        $this->assertStringNotContainsString('audio_base64', json_encode($outputPayload));
    }

    public function test_status_and_transcript_payloads_use_shared_pruned_audio_linkage_detection(): void
    {
        $session = S2sSession::query()->create([
            'title' => 'Partial Pruned Audio Probe',
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

        $segment = S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 1800,
            'source_language' => 'en-IN',
            'source_text' => 'Archived source audio was pruned.',
            'source_audio_path' => null,
            'status' => 'processed',
            'qa_state' => 'passed',
            'qa_checked_at' => now(),
            'engine_meta' => [
                'input_audio' => [
                    'disk' => 's2s_input_audio',
                    'stored_size' => 2048,
                    'compression' => 'gzip',
                    'pruned_reason' => 'retention_policy',
                    'pruned_original_path' => 's2s/devices/mic/2026-05-01/10/sessions/1/segments/1/source.wav.gz',
                    'pruned_stored_size' => 2048,
                ],
            ],
        ]);

        $this->assertTrue($segment->fresh()->hasPrunedSourceAudioRecord());

        $statusResponse = $this->getJson("/speech-to-speech/sessions/{$session->id}/status")
            ->assertOk()
            ->assertJsonPath('segments.0.source_audio.path', null)
            ->assertJsonPath('segments.0.source_audio.download_url', null)
            ->assertJsonPath('segments.0.source_audio.archive_layout.day', '2026-05-01')
            ->assertJsonPath('segments.0.source_audio.pruned', true)
            ->assertJsonPath('segments.0.source_audio.pruned_at', null)
            ->assertJsonPath('segments.0.source_audio.pruned_reason', 'retention_policy')
            ->assertJsonPath('segments.0.source_audio.pruned_stored_size', 2048);
        $this->assertStringNotContainsString('pruned_original_path', $statusResponse->getContent());
        $this->assertStringNotContainsString('2026-05-01/10/sessions/1/segments/1/source.wav.gz', $statusResponse->getContent());

        $transcriptResponse = $this->getJson("/speech-to-speech/sessions/{$session->id}/transcript.json")
            ->assertOk()
            ->assertJsonPath('segments.0.source_audio.path', null)
            ->assertJsonPath('segments.0.source_audio.download_url', null)
            ->assertJsonPath('segments.0.source_audio.archive_layout.hour', '10')
            ->assertJsonPath('segments.0.source_audio.pruned', true)
            ->assertJsonPath('segments.0.source_audio.pruned_at', null)
            ->assertJsonPath('segments.0.source_audio.pruned_reason', 'retention_policy')
            ->assertJsonPath('segments.0.source_audio.pruned_stored_size', 2048);
        $this->assertStringNotContainsString('pruned_original_path', $transcriptResponse->getContent());
        $this->assertStringNotContainsString('2026-05-01/10/sessions/1/segments/1/source.wav.gz', $transcriptResponse->getContent());
    }

    public function test_status_route_can_scope_payload_to_requested_sequence(): void
    {
        $session = S2sSession::query()->create([
            'title' => 'Scoped Status Probe',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'archive_meta' => ['segments' => 3],
            'status' => 'processing',
            'started_at' => now(),
        ]);

        foreach ([1, 2, 3] as $sequence) {
            $segment = S2sSegment::query()->create([
                'session_id' => $session->id,
                'sequence_no' => $sequence,
                'start_ms' => ($sequence - 1) * 3000,
                'end_ms' => $sequence * 3000,
                'source_language' => 'en-IN',
                'source_text' => 'Segment '.$sequence,
                'status' => $sequence === 2 ? 'processed' : 'processing',
                'engine_meta' => ['dispatch' => ['server_latency_ms' => 300 + $sequence]],
            ]);

            S2sOutput::query()->create([
                'session_id' => $session->id,
                'segment_id' => $segment->id,
                'language_code' => 'hi-IN',
                'channel_name' => 'Hindi',
                'status' => $sequence === 2 ? 'completed' : 'provider_pending',
                'text_output' => 'Output '.$sequence,
            ]);
        }

        $payload = $this->getJson("/speech-to-speech/sessions/{$session->id}/status?sequence_no=2")
            ->assertOk()
            ->assertJsonPath('archive_meta.segments', 3)
            ->assertJsonCount(1, 'segments')
            ->assertJsonPath('segments.0.sequence_no', 2)
            ->assertJsonPath('segments.0.source_text', 'Segment 2')
            ->json();

        $this->assertSame([2], array_column($payload['segments'], 'sequence_no'));
        $this->assertStringNotContainsString('Segment 1', json_encode($payload));
        $this->assertStringNotContainsString('Segment 3', json_encode($payload));
    }

    public function test_status_route_normalizes_and_bounds_output_error_messages(): void
    {
        $session = S2sSession::query()->create([
            'title' => 'Provider Error Probe',
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
            'start_ms' => 0,
            'end_ms' => 1000,
            'source_language' => 'en-IN',
            'source_text' => 'Provider error probe.',
            'status' => 'processed',
        ]);

        S2sOutput::query()->create([
            'session_id' => $session->id,
            'segment_id' => $segment->id,
            'language_code' => 'hi-IN',
            'channel_name' => 'Hindi',
            'status' => 'provider_error',
            'text_output' => null,
            'audio_output_path' => null,
            'output_meta' => [
                'tts_fallback' => [
                    'error' => "  TTS provider timed out.\n\t".str_repeat('Retry after gateway saturation. ', 12),
                ],
            ],
        ]);

        $message = $this->getJson("/speech-to-speech/sessions/{$session->id}/status")
            ->assertOk()
            ->json('segments.0.outputs.0.error_message');

        $this->assertIsString($message);
        $this->assertSame(240, strlen($message));
        $this->assertStringStartsWith('TTS provider timed out. Retry after gateway saturation.', $message);
        $this->assertStringNotContainsString("\n", $message);
        $this->assertStringNotContainsString("\t", $message);
    }

    public function test_scoped_status_refreshes_only_requested_pending_segment(): void
    {
        $pipeline = file_get_contents(app_path('Modules/SpeechToSpeech/Services/SarvamSpeechPipeline.php'));
        $this->assertIsString($pipeline);
        $this->assertStringContainsString('->where(\'sequence_no\', $sequenceNo)', $pipeline);
        $this->assertStringContainsString('$session->loadMissing([\'segments.outputs\']);', $pipeline);

        Http::fake(function (Request $request) {
            $url = (string) $request->url();
            if (str_contains($url, '/poll/2')) {
                return Http::response([
                    'status' => 'completed',
                    'outputs' => [[
                        'language_code' => 'hi-IN',
                        'status' => 'completed',
                        'text_output' => 'Segment 2 ready.',
                    ]],
                ], 200);
            }

            return Http::response(['status' => 'processing'], 200);
        });

        $session = S2sSession::query()->create([
            'title' => 'Scoped Refresh Probe',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'processing',
            'started_at' => now(),
        ]);

        foreach ([1, 2, 3] as $sequence) {
            $segment = S2sSegment::query()->create([
                'session_id' => $session->id,
                'sequence_no' => $sequence,
                'start_ms' => ($sequence - 1) * 3000,
                'end_ms' => $sequence * 3000,
                'source_language' => 'en-IN',
                'source_text' => 'Segment '.$sequence,
                'status' => 'processing',
                'engine_meta' => [
                    'dispatch' => [
                        'response' => ['poll_url' => "http://ml-gateway:8000/poll/{$sequence}"],
                    ],
                ],
            ]);

            S2sOutput::query()->create([
                'session_id' => $session->id,
                'segment_id' => $segment->id,
                'language_code' => 'hi-IN',
                'channel_name' => 'Hindi',
                'status' => 'provider_pending',
                'text_output' => 'Pending '.$sequence,
                'output_meta' => ['audio_output_supported' => false],
            ]);
        }

        $this->getJson("/speech-to-speech/sessions/{$session->id}/status?sequence_no=2")
            ->assertOk()
            ->assertJsonCount(1, 'segments')
            ->assertJsonPath('segments.0.sequence_no', 2)
            ->assertJsonPath('segments.0.outputs.0.text_output', 'Pending 2');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), '/poll/2'));
        Http::assertNotSent(fn (Request $request): bool => str_contains((string) $request->url(), '/poll/1'));
        Http::assertNotSent(fn (Request $request): bool => str_contains((string) $request->url(), '/poll/3'));

        $this->assertDatabaseHas('s2s_outputs', [
            'session_id' => $session->id,
            'language_code' => 'hi-IN',
            'text_output' => 'Pending 1',
        ]);
        $this->assertDatabaseHas('s2s_outputs', [
            'session_id' => $session->id,
            'language_code' => 'hi-IN',
            'text_output' => 'Pending 3',
        ]);
    }
}
