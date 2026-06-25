<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\SpeechToSpeech\Jobs\RecheckSegmentJob;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use App\Modules\SpeechToSpeech\Services\Recheck\FailedRecheckRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FailedRecheckRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_picks_up_failed_segment_past_cool_down_under_attempt_cap(): void
    {
        Queue::fake();
        $session = $this->makeSession();
        $segment = $this->seedSegment($session, [
            'qa_state' => 'failed',
            'qa_attempts' => 1,
            'qa_checked_at' => Carbon::now()->subSeconds(600),
            'source_audio_path' => 's2s/seg-1.wav',
        ]);

        $service = new FailedRecheckRetryService(maxAttempts: 3, coolDownSeconds: 300, batchSize: 50);

        $dispatched = $service->dispatchEligible();

        $this->assertSame(1, $dispatched);
        Queue::assertPushed(RecheckSegmentJob::class, fn ($job) => $job->segmentId === $segment->id);
    }

    public function test_skips_segments_at_or_above_max_attempts(): void
    {
        Queue::fake();
        $session = $this->makeSession();
        $this->seedSegment($session, [
            'qa_state' => 'failed',
            'qa_attempts' => 3,
            'qa_checked_at' => Carbon::now()->subSeconds(600),
            'source_audio_path' => 's2s/seg-2.wav',
        ]);

        $service = new FailedRecheckRetryService(maxAttempts: 3, coolDownSeconds: 300, batchSize: 50);

        $this->assertSame(0, $service->dispatchEligible());
        Queue::assertNotPushed(RecheckSegmentJob::class);
    }

    public function test_skips_segments_inside_cool_down_window(): void
    {
        Queue::fake();
        $session = $this->makeSession();
        $this->seedSegment($session, [
            'qa_state' => 'failed',
            'qa_attempts' => 1,
            'qa_checked_at' => Carbon::now()->subSeconds(120),
            'source_audio_path' => 's2s/seg-3.wav',
        ]);

        $service = new FailedRecheckRetryService(maxAttempts: 3, coolDownSeconds: 300, batchSize: 50);

        $this->assertSame(0, $service->dispatchEligible());
        Queue::assertNotPushed(RecheckSegmentJob::class);
    }

    public function test_skips_segments_in_other_states(): void
    {
        Queue::fake();
        $session = $this->makeSession();
        foreach (['passed', 'drift', 'corrected', 'skipped', 'pending'] as $state) {
            $this->seedSegment($session, [
                'qa_state' => $state,
                'qa_attempts' => 1,
                'qa_checked_at' => Carbon::now()->subSeconds(600),
                'source_audio_path' => 's2s/seg.wav',
            ]);
        }

        $service = new FailedRecheckRetryService(maxAttempts: 3, coolDownSeconds: 300, batchSize: 50);

        $this->assertSame(0, $service->dispatchEligible());
        Queue::assertNotPushed(RecheckSegmentJob::class);
    }

    public function test_respects_batch_size_cap(): void
    {
        Queue::fake();
        $session = $this->makeSession();
        for ($i = 1; $i <= 5; $i++) {
            $this->seedSegment($session, [
                'sequence_no' => $i,
                'qa_state' => 'failed',
                'qa_attempts' => 0,
                'qa_checked_at' => Carbon::now()->subSeconds(600),
                'source_audio_path' => "s2s/seg-{$i}.wav",
            ]);
        }

        $service = new FailedRecheckRetryService(maxAttempts: 3, coolDownSeconds: 300, batchSize: 2);

        $this->assertSame(2, $service->dispatchEligible());
        Queue::assertPushed(RecheckSegmentJob::class, 2);
    }

    public function test_skips_segments_without_source_audio_path(): void
    {
        Queue::fake();
        $session = $this->makeSession();
        $this->seedSegment($session, [
            'qa_state' => 'failed',
            'qa_attempts' => 1,
            'qa_checked_at' => Carbon::now()->subSeconds(600),
            'source_audio_path' => null,
        ]);

        $service = new FailedRecheckRetryService(maxAttempts: 3, coolDownSeconds: 300, batchSize: 50);

        $this->assertSame(0, $service->dispatchEligible());
        Queue::assertNotPushed(RecheckSegmentJob::class);
    }

    private function makeSession(): S2sSession
    {
        return S2sSession::query()->create([
            'title' => 'Retry Test',
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
     * @param  array{
     *   qa_state:string,
     *   qa_attempts:int,
     *   qa_checked_at:\Illuminate\Support\Carbon,
     *   source_audio_path:?string,
     *   sequence_no?:int
     * }  $attrs
     */
    private function seedSegment(S2sSession $session, array $attrs): S2sSegment
    {
        return S2sSegment::query()->create([
            'session_id' => $session->id,
            'sequence_no' => $attrs['sequence_no'] ?? (S2sSegment::query()->where('session_id', $session->id)->max('sequence_no') + 1) ?: 1,
            'source_text' => 'test transcript',
            'source_audio_path' => $attrs['source_audio_path'],
            'qa_state' => $attrs['qa_state'],
            'qa_attempts' => $attrs['qa_attempts'],
            'qa_checked_at' => $attrs['qa_checked_at'],
        ]);
    }
}
