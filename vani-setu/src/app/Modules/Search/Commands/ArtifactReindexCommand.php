<?php

namespace App\Modules\Search\Commands;

use App\Modules\Search\Services\SearchIndexer;
use Illuminate\Console\Command;

class ArtifactReindexCommand extends Command
{
    protected $signature = 'search:reindex-artifacts {--chunk=250}';

    protected $description = 'Backfill non-sensitive artifacts into the Meilisearch artifacts index.';

    public function handle(SearchIndexer $indexer): int
    {
        $count = $indexer->reindexArtifacts((int) $this->option('chunk'));

        $this->info("Indexed {$count} artifacts.");

        return self::SUCCESS;
    }
}
