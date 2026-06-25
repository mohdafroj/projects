<?php

namespace App\Modules\SpeechToSpeech\Commands;

use App\Modules\SpeechToSpeech\Services\Recheck\FailedRecheckRetryService;
use Illuminate\Console\Command;

class RecheckRetryFailedCommand extends Command
{
    protected $signature = 's2s:recheck-retry-failed
        {--dry : Just count eligible segments, do not dispatch}';

    protected $description = 'Self-heal the recheck engine: re-dispatch segments stuck in qa_state=failed once upstream has had time to recover. Intended to run on a 5-minute schedule.';

    public function handle(FailedRecheckRetryService $service): int
    {
        if ($this->option('dry')) {
            $count = $service->eligibleQuery()->count();
            $this->info("Eligible failed segments: {$count}");
            return self::SUCCESS;
        }

        $dispatched = $service->dispatchEligible();
        $this->info("Re-dispatched {$dispatched} failed segment(s) for recheck.");
        return self::SUCCESS;
    }
}
