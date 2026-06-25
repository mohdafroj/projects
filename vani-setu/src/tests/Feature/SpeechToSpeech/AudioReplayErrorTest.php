<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use App\Modules\SpeechToSpeech\Services\Recheck\InternalAudioUrlSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioReplayErrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.reporter_audio_disk' => 's2s_input_audio']);
        Storage::fake('s2s_input_audio');
    }

    public function test_public_audio_replay_returns_non_500_for_corrupt_compressed_audio(): void
    {
        $segment = $this->corruptCompressedSegment();

        $this->getJson(route('public.s2s.segments.audio', ['segment' => $segment->id]))
            ->assertStatus(422)
            ->assertJsonPath('error', 'audio_unreadable');
    }

    public function test_internal_audio_replay_returns_non_500_for_corrupt_compressed_audio(): void
    {
        $segment = $this->corruptCompressedSegment();
        $url = app(InternalAudioUrlSigner::class)->url($segment->id);
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        $this->getJson($path.'?'.$query)
            ->assertStatus(422)
            ->assertJsonPath('error', 'audio_unreadable');
    }

    private function corruptCompressedSegment(): S2sSegment
    {
        $session = S2sSession::query()->create([
            'title' => 'Corrupt Audio Replay',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'finished',
            'started_at' => now(),
        ]);

        $path = 's2s/'.$session->id.'/segments/1/source.wav.gz';
        Storage::disk('s2s_input_audio')->put($path, 'not-a-valid-gzip-payload');

        return S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 3000,
            'source_language' => 'en-IN',
            'source_text' => 'Replay should not 500.',
            'source_audio_path' => $path,
            'status' => 'processed',
            'engine_meta' => [
                'input_audio' => [
                    'disk' => 's2s_input_audio',
                    'path' => $path,
                    'mime_type' => 'audio/wav',
                    'original_name' => 'source.wav',
                    'compression' => 'gzip',
                ],
            ],
        ]);
    }
}
