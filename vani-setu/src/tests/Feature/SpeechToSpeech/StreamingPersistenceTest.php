<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StreamingPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('vani_audio');
        Storage::fake('s2s_input_audio');
        Storage::fake('public');
    }

    public function test_streaming_frames_are_persisted_for_qa_exports_and_replay(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'services.s2s.streaming_tts' => true,
            'services.sarvam.voice_pipeline_url' => 'http://ml-gateway:8000/v1/speech-to-speech',
        ]);

        Http::fake([
            'http://ml-gateway:8000/v1/speech-to-speech/stream' => Http::response(implode("\n\n", [
                'event: stt'."\n".'data: {"transcript":"Budget discussion is underway.","detected_language":"en-IN"}',
                'event: translation'."\n".'data: {"language_code":"hi-IN","text":"बजट चर्चा चल रही है।","translation_degraded":false}',
                'event: audio'."\n".'data: {"language_code":"hi-IN","audio_url":"https://audio.test/hi.wav","audio_key":"speech_to_speech_audio/2026/05/28/hi.wav","sentence_index":1,"sentence_text":"बजट चर्चा चल रही है।","total_sentences":1}',
                'event: done'."\n".'data: {}',
            ])."\n\n", 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $session = $this->postJson('/speech-to-speech/sessions', [
            'title' => 'Streaming Persistence',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
        ])->assertCreated()->json('session');

        $response = $this->post("/speech-to-speech/sessions/{$session['id']}/segments/stream", [
            'sequence_no' => 1,
            'source_language' => 'auto',
            'source_text' => 'Provider transcript will replace this.',
        ]);

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('event: archive', $content);
        $this->assertStringContainsString('"start_ms":0', $content);
        $this->assertStringContainsString('"end_ms":0', $content);
        $this->assertStringContainsString('"download_url":null', $content);
        $this->assertStringContainsString('event: stt', $content);
        $this->assertStringContainsString('event: translation', $content);
        $this->assertStringContainsString('event: audio', $content);

        $segment = S2sSegment::query()->where('session_id', $session['id'])->firstOrFail();
        $this->assertSame('processed', $segment->status);
        $this->assertSame('Budget discussion is underway.', $segment->source_text);
        $this->assertSame('en-IN', $segment->source_language);
        $this->assertSame('बजट चर्चा चल रही है।', data_get($segment->translated_segments, 'hi-IN.text_output'));
        $this->assertSame('https://audio.test/hi.wav', data_get($segment->translated_segments, 'hi-IN.audio_output_path'));
        $this->assertSame('stream', data_get($segment->engine_meta, 'dispatch.response.mode'));

        $output = S2sOutput::query()->where('segment_id', $segment->id)->where('language_code', 'hi-IN')->firstOrFail();
        $this->assertSame('completed', $output->status);
        $this->assertSame('बजट चर्चा चल रही है।', $output->text_output);
        $this->assertSame('https://audio.test/hi.wav', $output->audio_output_path);
        $this->assertSame('speech_to_speech_audio/2026/05/28/hi.wav', data_get($output->output_meta, 'provider_payload.audio_key'));

        $this->assertSame(1, S2sSession::query()->findOrFail($session['id'])->archive_meta['segments']);
    }
}
