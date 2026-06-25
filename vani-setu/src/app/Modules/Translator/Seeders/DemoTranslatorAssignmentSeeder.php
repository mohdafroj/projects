<?php

namespace App\Modules\Translator\Seeders;

use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use App\Modules\Translator\Models\TranslatorAssignment;
use Illuminate\Database\Seeder;

class DemoTranslatorAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $sitting = Sitting::query()->first();
        $slot = Slot::query()->where('code', '1A')->first() ?? Slot::query()->first();
        $translator = User::query()->where('employee_id', 'TRN-EN-001')->first();

        if (! $sitting || ! $slot || ! $translator) {
            return;
        }

        TranslatorAssignment::query()->updateOrCreate(
            ['slot_id' => $slot->id, 'translator_user_id' => $translator->id, 'language_pair' => 'en_to_hi'],
            [
                'sitting_id' => $sitting->id,
                'status' => 'open',
                'ai_translation_meta' => ['source' => 'demo_sitting'],
            ],
        );
    }
}
