<?php

namespace App\Modules\SpeechToSpeech\Commands;

use App\Modules\SpeechToSpeech\Jobs\RecheckSegmentJob;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use App\Modules\SpeechToSpeech\Services\Recheck\RecheckService;
use Illuminate\Console\Command;

class RecheckCommand extends Command
{
    protected $signature = 's2s:recheck {session_id : Session ID to recheck}
        {--force : Re-run for segments already in passed/drift/corrected/failed states}
        {--sync : Run inline instead of dispatching to the s2s-recheck queue}';

    protected $description = 'Run the QA recheck pass over all segments in an S2S session: re-transcribe, compare against the live transcript, persist drift verdicts.';

    public function handle(RecheckService $service): int
    {
        $sessionId = (int) $this->argument('session_id');
        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync');

        $session = S2sSession::query()->find($sessionId);
        if ($session === null) {
            $this->error("Session {$sessionId} not found.");
            return self::FAILURE;
        }

        $query = S2sSegment::query()
            ->where('session_id', $sessionId)
            ->whereNotNull('source_audio_path')
            ->orderBy('sequence_no');

        if (! $force) {
            $query->where('qa_state', 'pending');
        }

        $segments = $query->get();
        $count = $segments->count();
        if ($count === 0) {
            $this->info("Session {$sessionId}: no segments eligible for recheck.");
            return self::SUCCESS;
        }

        $this->info("Session {$sessionId}: dispatching recheck for {$count} segment(s) (sync=".($sync ? 'true' : 'false').").");

        foreach ($segments as $segment) {
            if ($sync) {
                $service->recheckSegment($segment);
            } else {
                RecheckSegmentJob::dispatch($segment->id);
            }
        }

        return self::SUCCESS;
    }
}
