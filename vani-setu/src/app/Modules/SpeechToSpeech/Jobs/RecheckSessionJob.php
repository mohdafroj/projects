<?php

namespace App\Modules\SpeechToSpeech\Jobs;

use App\Modules\SpeechToSpeech\Models\S2sSegment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecheckSessionJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;
    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $sessionId,
        public readonly bool $force = false,
    ) {
        $this->onQueue('s2s-recheck');
    }

    public function uniqueId(): string
    {
        return 's2s_session_recheck:'.$this->sessionId;
    }

    public function handle(): void
    {
        $query = S2sSegment::query()
            ->where('session_id', $this->sessionId)
            ->whereNotNull('source_audio_path')
            ->orderBy('sequence_no');

        if (! $this->force) {
            $query->where('qa_state', 'pending');
        }

        $query->get(['id'])->each(function (S2sSegment $segment): void {
            RecheckSegmentJob::dispatch($segment->id);
        });
    }
}
