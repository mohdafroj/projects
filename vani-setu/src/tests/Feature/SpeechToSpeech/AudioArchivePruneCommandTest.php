<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Search\Models\StoredArtifact;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioArchivePruneCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.reporter_audio_disk' => 's2s_input_audio',
            'services.s2s.audio_archive_retention_days' => 30,
            'services.s2s.audio_archive_prune_batch' => 500,
        ]);
        Storage::fake('s2s_input_audio');
    }

    public function test_dry_run_reports_old_finished_source_audio_without_deleting_it(): void
    {
        $segment = $this->storedSegment(now()->subDays(45));

        $this->artisan('s2s:prune-audio-archive', ['--dry' => true])
            ->expectsOutputToContain('Would prune 1 of 1 eligible source-audio segment(s)')
            ->assertSuccessful();

        Storage::disk('s2s_input_audio')->assertExists($segment->source_audio_path);
        $this->assertNotNull($segment->fresh()->source_audio_path);
    }

    public function test_prune_deletes_only_finished_sessions_past_retention_and_marks_catalog(): void
    {
        $old = $this->storedSegment(now()->subDays(45), 's2s/devices/line-in/device-a/2026-04-01/10/sessions/1/segments/1/source.wav.gz');
        $recent = $this->storedSegment(now()->subDays(5), 's2s/devices/line-in/device-a/2026-05-23/10/sessions/2/segments/1/source.wav.gz');
        $active = $this->storedSegment(null, 's2s/devices/line-in/device-a/2026-05-28/10/sessions/3/segments/1/source.wav.gz');

        $this->artisan('s2s:prune-audio-archive')
            ->expectsOutputToContain('Pruned 1 of 1 eligible source-audio segment(s)')
            ->assertSuccessful();

        Storage::disk('s2s_input_audio')->assertMissing('s2s/devices/line-in/device-a/2026-04-01/10/sessions/1/segments/1/source.wav.gz');
        Storage::disk('s2s_input_audio')->assertExists($recent->source_audio_path);
        Storage::disk('s2s_input_audio')->assertExists($active->source_audio_path);

        $old = $old->fresh();
        $this->assertNull($old->source_audio_path);
        $this->assertSame('retention_policy', data_get($old->engine_meta, 'input_audio.pruned_reason'));
        $this->assertSame('s2s/devices/line-in/device-a/2026-04-01/10/sessions/1/segments/1/source.wav.gz', data_get($old->engine_meta, 'input_audio.pruned_original_path'));
        $this->assertSame('device', data_get($old->engine_meta, 'input_audio.archive_layout.tags.0'));
        $this->assertSame('2026-04-01', data_get($old->engine_meta, 'input_audio.archive_layout.day'));
        $this->assertSame('10', data_get($old->engine_meta, 'input_audio.archive_layout.hour'));

        $statusResponse = $this->getJson("/speech-to-speech/sessions/{$old->session_id}/status")
            ->assertOk()
            ->assertJsonPath('segments.0.source_audio.path', null)
            ->assertJsonPath('segments.0.source_audio.download_url', null)
            ->assertJsonPath('segments.0.source_audio.pruned', true)
            ->assertJsonPath('segments.0.source_audio.pruned_reason', 'retention_policy')
            ->assertJsonPath('segments.0.source_audio.pruned_stored_size', strlen('compressed-audio-'.$old->session_id));
        $this->assertStringNotContainsString('pruned_original_path', $statusResponse->getContent());
        $this->assertStringNotContainsString('2026-04-01/10/sessions/1/segments/1/source.wav.gz', $statusResponse->getContent());

        $transcriptResponse = $this->getJson("/speech-to-speech/sessions/{$old->session_id}/transcript.json")
            ->assertOk()
            ->assertJsonPath('segments.0.source_audio.path', null)
            ->assertJsonPath('segments.0.source_audio.download_url', null)
            ->assertJsonPath('segments.0.source_audio.pruned', true)
            ->assertJsonPath('segments.0.source_audio.pruned_reason', 'retention_policy')
            ->assertJsonPath('segments.0.source_audio.pruned_stored_size', strlen('compressed-audio-'.$old->session_id));
        $this->assertStringNotContainsString('pruned_original_path', $transcriptResponse->getContent());
        $this->assertStringNotContainsString('2026-04-01/10/sessions/1/segments/1/source.wav.gz', $transcriptResponse->getContent());

        $artifact = StoredArtifact::query()->findOrFail(data_get($old->engine_meta, 'input_audio.artifact_id'));
        $this->assertNull($artifact->storage_path);
        $this->assertSame(0, $artifact->size_bytes);
        $this->assertContains('pruned', $artifact->tags);
        $this->assertSame('s2s/devices/line-in/device-a/2026-04-01/10/sessions/1/segments/1/source.wav.gz', $artifact->metadata['retention_original_path']);
    }

    private function storedSegment(?\Illuminate\Support\Carbon $finishedAt, string $path = 's2s/devices/line-in/device-a/2026-04-01/10/sessions/1/segments/1/source.wav.gz'): S2sSegment
    {
        $session = S2sSession::query()->create([
            'title' => 'Archive Prune Test',
            'mode' => 'live',
            'input_source' => 'line_in',
            'listener_scope' => 'inside_house',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => $finishedAt ? 'finished' : 'live',
            'started_at' => $finishedAt?->copy()->subHour() ?? now()->subHour(),
            'finished_at' => $finishedAt,
        ]);

        $contents = 'compressed-audio-'.$session->id;
        Storage::disk('s2s_input_audio')->put($path, $contents);
        $artifact = StoredArtifact::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => basename($path),
            'stored_disk' => 's2s_input_audio',
            'storage_path' => $path,
            'storage_uri' => 's2s_input_audio://'.$path,
            'mime_type' => 'application/gzip',
            'extension' => 'gz',
            'media_family' => 'archive',
            'source_module' => 'speech_to_speech',
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'tags' => ['s2s', 'audio-upload', 'source-audio', 'compressed'],
            'metadata' => ['original_name' => 'source.wav'],
            'classification_status' => 'ready',
            'search_status' => 'pending',
            'last_hygiene_at' => now(),
        ]);

        return S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => 1,
            'start_ms' => 0,
            'end_ms' => 2000,
            'source_text' => 'Test segment',
            'source_audio_path' => $path,
            'status' => 'processed',
            'engine_meta' => [
                'input_audio' => [
                    'disk' => 's2s_input_audio',
                    'path' => $path,
                    'stored_size' => strlen($contents),
                    'artifact_id' => $artifact->id,
                    'compression' => 'gzip',
                ],
            ],
            'qa_state' => 'passed',
        ]);
    }
}
