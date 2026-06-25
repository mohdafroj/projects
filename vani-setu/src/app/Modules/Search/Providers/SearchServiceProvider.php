<?php

namespace App\Modules\Search\Providers;

use App\Modules\Core\Models\Block;
use App\Modules\Search\Commands\ArtifactAuditCommand;
use App\Modules\Search\Commands\ArtifactHygieneCommand;
use App\Modules\Search\Commands\ArtifactReindexCommand;
use App\Modules\Search\Commands\SearchReindexCommand;
use App\Modules\Search\Observers\BlockSearchObserver;
use Illuminate\Support\ServiceProvider;

class SearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Block::observe(BlockSearchObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ArtifactAuditCommand::class,
                ArtifactHygieneCommand::class,
                ArtifactReindexCommand::class,
                SearchReindexCommand::class,
            ]);
        }
    }
}
