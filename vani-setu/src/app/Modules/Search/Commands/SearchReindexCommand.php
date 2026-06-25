<?php

namespace App\Modules\Search\Commands;

use App\Modules\Search\Services\SearchIndexer;
use Illuminate\Console\Command;

class SearchReindexCommand extends Command
{
    protected $signature = 'search:reindex {--chunk=250}';

    protected $description = 'Backfill transcript blocks into the Meilisearch blocks index.';

    public function handle(SearchIndexer $indexer): int
    {
        $count = $indexer->reindexAll((int) $this->option('chunk'));

        $this->info("Indexed {$count} blocks.");

        return self::SUCCESS;
    }
}
