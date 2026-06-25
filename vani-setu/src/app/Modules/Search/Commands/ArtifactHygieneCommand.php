<?php

namespace App\Modules\Search\Commands;

use App\Modules\Search\Models\StoredArtifact;
use App\Modules\Search\Services\ArtifactCatalogService;
use App\Modules\Search\Services\SearchIndexer;
use Illuminate\Console\Command;

class ArtifactHygieneCommand extends Command
{
    protected $signature = 'artifacts:hygiene {--limit=200}';

    protected $description = 'Hydrate and index non-sensitive artifacts during low-load periods.';

    public function handle(ArtifactCatalogService $catalog, SearchIndexer $indexer): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;

        StoredArtifact::query()
            ->where('sensitivity_classification', 'non_sensitive')
            ->where(function ($query) {
                $query->where('classification_status', 'pending')
                    ->orWhere('search_status', 'pending')
                    ->orWhereNull('indexed_at');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (StoredArtifact $artifact) use ($catalog, $indexer, &$processed) {
                $artifact = $catalog->hydrateForHygiene($artifact);

                if ($artifact->search_eligible && $artifact->search_status !== 'blocked') {
                    $indexer->indexArtifact($artifact);
                    $artifact->forceFill([
                        'search_status' => 'indexed',
                        'indexed_at' => now(),
                    ])->save();
                }

                $processed++;
            });

        $this->info("Processed {$processed} artifacts.");

        return self::SUCCESS;
    }
}
