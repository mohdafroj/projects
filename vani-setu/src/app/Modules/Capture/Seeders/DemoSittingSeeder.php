<?php

namespace App\Modules\Capture\Seeders;

use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use Illuminate\Database\Seeder;

class DemoSittingSeeder extends Seeder
{
    public function run(): void
    {
        $sitting = Sitting::query()->updateOrCreate(
            ['session_no' => 2025, 'sitting_no' => 270],
            ['sitting_date' => '2025-12-11', 'status' => 'live', 'started_at' => null, 'ended_at' => null],
        );

        foreach ($this->slots() as $index => [$code, $topic]) {
            Slot::query()->updateOrCreate(
                ['sitting_id' => $sitting->id, 'code' => $code],
                ['start_offset_ms' => $index * 300000, 'duration_ms' => 300000, 'topic' => $topic, 'status' => 'open'],
            );
        }
    }

    private function slots(): array
    {
        return [
            ['1A', 'Zero Hour · NH75 Karnataka completion delays'],
            ['1B', 'Zero Hour · NEP rollout in Maharashtra'],
            ['1C', 'Zero Hour · West Bengal coastal erosion'],
            ['1D', 'Question Hour · Q1 Power sector reforms'],
            ['1E', 'Question Hour · Q2 Maharashtra industrial corridor'],
            ['1F', 'Question Hour · Q3 PMGSY rural roads'],
            ['2A', 'Question Hour · Q4 Defence procurement'],
            ['2B', 'Question Hour · Q5 Railway safety audits'],
            ['2C', 'Calling Attention · Air Quality NCR'],
            ['2D', 'Special Mention · Fishermen welfare'],
            ['2E', 'Papers Laid · Annual reports'],
            ['2F', 'Closing remarks · Adjournment'],
        ];
    }
}
