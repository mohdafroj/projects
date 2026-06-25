<?php

namespace App\Modules\SpeechToSpeech\Services\Recheck;

use App\Modules\SpeechToSpeech\Jobs\RecheckSegmentJob;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Self-healing pass over the recheck engine. Picks up segments where
 * the second-pass transcriber previously failed (Sarvam circuit open,
 * 5xx, network blip) AND we haven't exhausted the retry budget AND
 * enough time has passed for upstream to plausibly have recovered.
 * Re-dispatches RecheckSegmentJob; the existing RecheckService
 * increments qa_attempts and either flips the verdict or keeps it
 * 'failed' for the next pass.
 *
 * Intended caller: scheduled task in routes/console.php, every 5 min.
 */
class FailedRecheckRetryService
{
    public function __construct(
        private readonly int $maxAttempts,
        private readonly int $coolDownSeconds,
        private readonly int $batchSize,
    ) {}

    /**
     * Query the eligible failed-recheck segments.
     */
    public function eligibleQuery(): Builder
    {
        $cutoff = Carbon::now()->subSeconds($this->coolDownSeconds);

        return S2sSegment::query()
            ->where('qa_state', 'failed')
            ->where('qa_attempts', '<', $this->maxAttempts)
            ->whereNotNull('source_audio_path')
            ->where(function (Builder $q) use ($cutoff) {
                $q->whereNull('qa_checked_at')
                  ->orWhere('qa_checked_at', '<', $cutoff);
            })
            ->orderBy('qa_checked_at')
            ->limit($this->batchSize);
    }

    /**
     * Dispatch RecheckSegmentJob for every eligible segment. Returns
     * the number dispatched (callers log this for observability).
     */
    public function dispatchEligible(): int
    {
        $segments = $this->eligibleQuery()->get(['id', 'qa_attempts']);
        $count = 0;
        foreach ($segments as $segment) {
            RecheckSegmentJob::dispatch($segment->id);
            $count++;
        }
        return $count;
    }
}
