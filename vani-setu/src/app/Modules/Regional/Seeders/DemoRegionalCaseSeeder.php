<?php

namespace App\Modules\Regional\Seeders;

use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use App\Modules\Regional\Models\RegionalCase;
use Illuminate\Database\Seeder;

class DemoRegionalCaseSeeder extends Seeder
{
    public function run(): void
    {
        $sitting = Sitting::query()->first();
        $slot = Slot::query()->first();
        $specialist = User::query()->where('employee_id', 'TRN-TA-001')->first();

        if (! $sitting || ! $slot || ! $specialist) {
            return;
        }

        RegionalCase::query()->updateOrCreate(
            ['slot_id' => $slot->id, 'source_language' => 'ta', 'source_text' => 'தமிழில் உரை உள்ளது'],
            [
                'sitting_id' => $sitting->id,
                'specialist_user_id' => $specialist->id,
                'target_language' => 'hi',
                'detector' => 'unicode-script',
                'detection_confidence' => 0.95,
                'domain' => 'parliamentary',
                'status' => 'routed',
                'routing_meta' => ['language_pair' => 'ta_to_hi'],
            ],
        );
    }
}
