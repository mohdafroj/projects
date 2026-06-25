<?php

namespace App\Modules\SpeechToSpeech\Commands;

use App\Modules\SpeechToSpeech\Models\S2sSession;
use App\Modules\SpeechToSpeech\Services\Recheck\QaSummaryService;
use Illuminate\Console\Command;

class QaSummaryCommand extends Command
{
    protected $signature = 's2s:qa-summary {session_id : Session ID}
        {--samples=5 : Number of drift / corrected samples to show}
        {--json : Emit JSON instead of human-readable tables}';

    protected $description = 'Print the recheck-engine QA verdict distribution for a session, plus sample drift / corrected segments.';

    public function handle(QaSummaryService $service): int
    {
        $sessionId = (int) $this->argument('session_id');
        $session = S2sSession::query()->find($sessionId);
        if ($session === null) {
            $this->error("Session {$sessionId} not found.");
            return self::FAILURE;
        }

        $summary = $service->summarise($session, sampleLimit: (int) $this->option('samples'));

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Session %d — %d segment(s) (%d audio linked: %d active, %d pruned); last checked: %s',
            $summary['session_id'],
            $summary['total_segments'],
            $summary['audio_segments'],
            $summary['active_audio_segments'],
            $summary['pruned_audio_segments'],
            $summary['last_checked_at'] ?? '(never)',
        ));

        if ($summary['verdicts'] === []) {
            $this->warn('No verdicts yet — run `php artisan s2s:recheck '.$sessionId.'` first.');
            return self::SUCCESS;
        }

        $rows = array_map(fn (array $v) => [
            $v['state'],
            $v['count'],
            $v['avg_score'] ?? '—',
            $v['min_score'] ?? '—',
            $v['max_score'] ?? '—',
        ], $summary['verdicts']);
        $this->table(['state', 'count', 'avg_score', 'min_score', 'max_score'], $rows);

        if ($summary['sample_drift'] !== []) {
            $this->newLine();
            $this->line('<info>Sample drift cases (lowest scores first):</info>');
            $driftRows = array_map(fn (array $r) => [
                $r['segment_id'],
                $r['sequence_no'],
                $this->truncate((string) $r['source_text'], 60),
                $this->truncate((string) $r['second_pass'], 60),
                $r['score'] ?? '—',
            ], $summary['sample_drift']);
            $this->table(['seg', 'seq', 'live transcript', '2nd-pass transcript', 'score'], $driftRows);
        }

        if ($summary['sample_corrected'] !== []) {
            $this->newLine();
            $this->line('<info>Auto-corrected segments:</info>');
            $correctedRows = array_map(fn (array $r) => [
                $r['segment_id'],
                $r['sequence_no'],
                $this->truncate((string) $r['original'], 60),
                $this->truncate((string) $r['corrected'], 60),
                $r['score'] ?? '—',
            ], $summary['sample_corrected']);
            $this->table(['seg', 'seq', 'original', 'corrected', 'score'], $correctedRows);
        }

        return self::SUCCESS;
    }

    private function truncate(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
    }
}
