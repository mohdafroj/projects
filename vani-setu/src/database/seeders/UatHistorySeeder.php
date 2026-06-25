<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use Illuminate\Database\Seeder;

class UatHistorySeeder extends Seeder
{
    public function run(): void
    {
        $members = Member::query()->orderBy('id')->take(8)->get();
        if ($members->isEmpty()) {
            return;
        }

        foreach (range(1, 5) as $day) {
            $sitting = Sitting::query()->updateOrCreate(
                ['session_no' => 267, 'sitting_no' => 20 + $day],
                [
                    'sitting_date' => now()->subDays(10 - $day)->toDateString(),
                    'status' => 'closed',
                    'started_at' => now()->subDays(10 - $day)->setTime(11, 0),
                    'ended_at' => now()->subDays(10 - $day)->setTime(18, 0),
                ],
            );

            foreach (range(1, 12) as $slotIndex) {
                $slot = Slot::query()->updateOrCreate(
                    ['sitting_id' => $sitting->id, 'code' => 'H'.$slotIndex],
                    [
                        'start_offset_ms' => ($slotIndex - 1) * 300000,
                        'duration_ms' => 300000,
                        'topic' => 'UAT historical sitting '.$day.' slot '.$slotIndex,
                        'status' => 'committed_full',
                    ],
                );

                foreach (range(1, 2) as $sequence) {
                    $member = $members[($slotIndex + $sequence + $day) % $members->count()];
                    Block::query()->updateOrCreate(
                        ['slot_id' => $slot->id, 'sequence' => $sequence],
                        [
                            'start_ms' => $slot->start_offset_ms + (($sequence - 1) * 150000),
                            'end_ms' => $slot->start_offset_ms + ($sequence * 150000) - 1,
                            'original_lang' => $sequence === 1 ? 'en' : 'hi',
                            'chief_lang' => $sequence === 1 ? 'en' : 'hi',
                            'ai_action' => $sequence === 1 ? 'native' : 'translated',
                            'ai_text' => $member->name_en.' raised a historical UAT matter for comparison with manual floor version output.',
                            'text' => $member->name_en.' raised a historical UAT matter for comparison with manual floor version output.',
                            'translated_text' => null,
                            'member_id' => $member->id,
                            'custom_member_id' => null,
                            'version' => 1,
                            'reporter_edit_count' => 1,
                        ],
                    );
                }
            }
        }
    }
}
