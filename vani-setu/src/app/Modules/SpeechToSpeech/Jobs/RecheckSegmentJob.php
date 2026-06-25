<?php

namespace App\Modules\SpeechToSpeech\Jobs;

use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Services\Recheck\RecheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecheckSegmentJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;
    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $segmentId,
    ) {
        $this->onQueue('s2s-recheck');
    }

    public function uniqueId(): string
    {
        return 's2s_segment_recheck:'.$this->segmentId;
    }

    public function handle(RecheckService $service): void
    {
        $segment = S2sSegment::query()->find($this->segmentId);
        if ($segment === null) {
            return;
        }
        $service->recheckSegment($segment);
    }
}
