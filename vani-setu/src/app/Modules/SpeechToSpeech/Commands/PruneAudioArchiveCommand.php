<?php

namespace App\Modules\SpeechToSpeech\Commands;

use App\Modules\SpeechToSpeech\Services\S2sAudioArchive;
use Illuminate\Console\Command;

class PruneAudioArchiveCommand extends Command
{
    protected $signature = 's2s:prune-audio-archive
        {--days= : Retention window in days; defaults to services.s2s.audio_archive_retention_days}
        {--limit= : Maximum source-audio segments to process in this run}
        {--dry : Count and size eligible files without deleting them}';

    protected $description = 'Prune archived source audio for finished S2S sessions after the configured retention window, preserving transcript and audit metadata.';

    public function handle(S2sAudioArchive $archive): int
    {
        $days = (int) ($this->option('days') ?: config('services.s2s.audio_archive_retention_days', 30));
        $limit = (int) ($this->option('limit') ?: config('services.s2s.audio_archive_prune_batch', 500));

        if ($days <= 0) {
            $this->warn('Audio archive pruning is disabled because the retention window is not positive.');

            return self::SUCCESS;
        }

        $stats = $archive->pruneFinishedSourceAudio($days, $limit, (bool) $this->option('dry'));

        $verb = $stats['dry_run'] ? 'Would prune' : 'Pruned';
        $this->info(sprintf(
            '%s %d of %d eligible source-audio segment(s); released %d byte(s); missing %d; retained %d; cutoff %s.',
            $verb,
            $stats['pruned'],
            $stats['eligible'],
            $stats['bytes_released'],
            $stats['missing'],
            $stats['retained'],
            $stats['cutoff'],
        ));

        return self::SUCCESS;
    }
}
