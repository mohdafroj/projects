<?php

namespace App\Modules\Chief\Seeders;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Capture\Seeders\DemoSittingSeeder;
use App\Modules\Core\Models\Sitting;
use Illuminate\Database\Seeder;

class DemoChiefConsolidationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoSittingSeeder::class);

        $sitting = Sitting::query()
            ->where('session_no', 2025)
            ->where('sitting_no', 270)
            ->first();

        if (! $sitting) {
            return;
        }

        foreach ([['A', 0], ['B', 1800000]] as [$window, $offset]) {
            ChiefConsolidation::query()->updateOrCreate(
                ['sitting_id' => $sitting->id, 'window_code' => $window],
                ['starts_at_offset_ms' => $offset, 'duration_ms' => 1800000, 'status' => 'open'],
            );
        }
    }
}
