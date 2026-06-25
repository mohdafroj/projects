<?php

namespace Tests\Feature\LiveChamber;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveChamberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_snapshot_returns_current_floor_state_and_audits_access(): void
    {
        $user = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $member = Member::query()->firstOrFail();
        $sitting = Sitting::query()->create([
            'session_no' => 2026,
            'sitting_no' => 88,
            'sitting_date' => '2026-05-20',
            'status' => 'live',
            'started_at' => now()->subMinutes(3),
        ]);
        $slot = Slot::query()->create([
            'sitting_id' => $sitting->id,
            'code' => 'LC-1',
            'start_offset_ms' => 0,
            'duration_ms' => 600000,
            'topic' => 'Live chamber floor',
            'status' => 'in_progress',
        ]);
        $block = Block::query()->create([
            'slot_id' => $slot->id,
            'sequence' => 1,
            'start_ms' => 0,
            'end_ms' => 45000,
            'original_lang' => 'en',
            'chief_lang' => 'en',
            'ai_action' => 'native',
            'ai_text' => 'Live ASR feed text.',
            'text' => 'Live chamber corrected text.',
            'member_id' => $member->id,
        ]);
        SlotAssignment::query()->create([
            'slot_id' => $slot->id,
            'user_id' => $user->id,
            'assignee_user_id' => $user->id,
            'lang_role' => 'en',
            'status' => 'in_progress',
            'workflow_stage' => 'reporter',
            'last_workflow_action_at' => now(),
        ]);

        app(AuditLogger::class)->log('asr.block.ingested', $block, [
            'slot_id' => $slot->id,
            'confidence' => 0.91,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/live-chamber/snapshot')
            ->assertOk()
            ->assertJsonPath('current_slot.code', 'LC-1')
            ->assertJsonPath('speaker.id', $member->id)
            ->assertJsonPath('capture.status', 'in_progress')
            ->assertJsonPath('asr.confidence', 0.91)
            ->assertJsonPath('asr.quality', 'high')
            ->assertJsonPath('recent_blocks.0.id', $block->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'live_chamber.snapshot.viewed']);
    }

    public function test_snapshot_returns_offline_shape_when_no_live_sitting_exists(): void
    {
        Sitting::query()->where('status', 'live')->update(['status' => 'closed']);
        Sanctum::actingAs(User::query()->where('employee_id', 'ADM-001')->firstOrFail());

        $this->getJson('/api/live-chamber/snapshot')
            ->assertOk()
            ->assertJsonPath('sitting', null)
            ->assertJsonPath('current_slot', null)
            ->assertJsonPath('capture.status', 'offline')
            ->assertJsonPath('asr.quality', 'unknown');

        $this->assertDatabaseHas('audit_logs', ['action' => 'live_chamber.snapshot.viewed']);
    }
}
