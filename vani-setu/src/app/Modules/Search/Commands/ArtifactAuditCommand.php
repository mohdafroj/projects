<?php

namespace App\Modules\Search\Commands;

use App\Modules\Search\Models\StoredArtifact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ArtifactAuditCommand extends Command
{
    protected $signature = 'artifacts:audit {--fail-on-orphans}';

    protected $description = 'Scan monitored storage locations for non-cataloged files.';

    public function handle(): int
    {
        $locations = config('services.artifact_catalog.monitored_locations', []);
        $orphans = [];

        foreach ($locations as $location) {
            $disk = is_array($location) ? ($location['disk'] ?? null) : null;
            $prefix = trim((string) (is_array($location) ? ($location['prefix'] ?? '') : ''), '/');
            if (! is_string($disk) || $disk === '') {
                continue;
            }

            try {
                $files = Storage::disk($disk)->allFiles($prefix);
            } catch (\Throwable $exception) {
                $this->warn("Skipping {$disk}: {$exception->getMessage()}");
                continue;
            }

            foreach ($files as $file) {
                $exists = StoredArtifact::query()
                    ->where('stored_disk', $disk)
                    ->where('storage_path', $file)
                    ->exists();

                if (! $exists) {
                    $orphans[] = "{$disk}:{$file}";
                }
            }
        }

        if ($orphans === []) {
            $this->info('No orphaned files detected.');

            return self::SUCCESS;
        }

        foreach ($orphans as $orphan) {
            $this->line($orphan);
        }

        $this->warn('Orphaned files detected: '.count($orphans));

        return $this->option('fail-on-orphans') ? self::FAILURE : self::SUCCESS;
    }
}
