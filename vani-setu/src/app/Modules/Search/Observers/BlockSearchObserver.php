<?php

namespace App\Modules\Search\Observers;

use App\Modules\Core\Models\Block;
use App\Modules\Search\Services\SearchIndexer;

class BlockSearchObserver
{
    public function created(Block $block): void
    {
        $this->index($block);
    }

    public function updated(Block $block): void
    {
        $this->index($block);
    }

    private function index(Block $block): void
    {
        try {
            app(SearchIndexer::class)->indexBlock($block);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
