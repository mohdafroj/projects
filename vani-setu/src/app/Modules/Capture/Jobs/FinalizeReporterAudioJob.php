<?php

namespace App\Modules\Capture\Jobs;

use App\Modules\Capture\Services\ReporterAudioFinalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeReporterAudioJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $slotId,
    ) {}

    public function uniqueId(): string
    {
        return 'slot:'.$this->slotId;
    }

    public function handle(ReporterAudioFinalizer $finalizer): void
    {
        $finalizer->finalizeBySlotId($this->slotId);
    }
}
