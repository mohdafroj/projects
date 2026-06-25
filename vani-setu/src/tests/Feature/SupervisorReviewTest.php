<?php

namespace Tests\Feature;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupervisorReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_sees_committed_lanes_in_queue(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->getJson('/api/supervisor/queue')
            ->assertOk()
            ->assertJsonFragment(['id' => $assignment->id, 'workflow_stage' => 'supervisor']);
    }

    public function test_supervisor_only_sees_lanes_matching_their_languages(): void
    {
        $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-HI-001'));

        $ids = collect($this->getJson('/api/supervisor/queue')->assertOk()->json('data'))->pluck('lang_role');

        $this->assertNotContains('en', $ids);
    }

    public function test_supervisor_cannot_see_uncommitted_lanes(): void
    {
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->getJson('/api/supervisor/queue')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_supervisor_can_forward_to_chief(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->postJson("/api/slot-assignments/{$assignment->id}/forward", ['note' => 'Clean lane.'])
            ->assertOk()
            ->assertJsonPath('assignment.workflow_stage', 'chief');
    }

    public function test_forward_writes_audit_action_workflow_forward_chief(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->postJson("/api/slot-assignments/{$assignment->id}/forward", ['note' => 'Clean lane.'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'capture.workflow.forward']);
    }

    public function test_forward_transitions_assignment_to_chief_stage(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->postJson("/api/slot-assignments/{$assignment->id}/forward", ['note' => 'Clean lane.'])->assertOk();

        $this->assertSame('chief', $assignment->fresh()->workflow_stage);
    }

    public function test_supervisor_can_return_to_reporter_with_reason(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->postJson("/api/slot-assignments/{$assignment->id}/return", ['reason' => 'Please correct speaker attribution.'])
            ->assertOk()
            ->assertJsonPath('assignment.workflow_stage', 'returned');
    }

    public function test_return_without_reason_returns_422(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->postJson("/api/slot-assignments/{$assignment->id}/return", [])
            ->assertStatus(422);
    }

    public function test_return_with_short_reason_returns_422(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->postJson("/api/slot-assignments/{$assignment->id}/return", ['reason' => 'short'])
            ->assertStatus(422);
    }

    public function test_return_writes_audit_action_workflow_return(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->postJson("/api/slot-assignments/{$assignment->id}/return", ['reason' => 'Please correct speaker attribution.'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'workflow.return']);
    }

    public function test_returned_lane_reverts_status_to_in_progress(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->postJson("/api/slot-assignments/{$assignment->id}/return", ['reason' => 'Please correct speaker attribution.'])->assertOk();

        $fresh = $assignment->fresh();
        $this->assertSame('in_progress', $fresh->status);
        $this->assertNull($fresh->committed_at);
        $this->assertNull($fresh->committed_audit_log_id);
    }

    public function test_returned_lane_appears_in_reporter_assignments_with_reason(): void
    {
        $assignment = $this->returnLane('1A', 'en', 'RPT-001', 'Please correct speaker attribution.');
        Sanctum::actingAs($this->user('RPT-001'));

        $this->getJson('/api/me/assignments')
            ->assertOk()
            ->assertJsonFragment(['id' => $assignment->id, 'workflow_stage' => 'returned'])
            ->assertJsonFragment(['reason' => 'Please correct speaker attribution.']);
    }

    public function test_reporter_can_edit_block_after_return(): void
    {
        $this->returnLane('1A', 'en', 'RPT-001', 'Please correct speaker attribution.');
        $block = $this->block('1A', 'en');
        Sanctum::actingAs($this->user('RPT-001'));

        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Corrected after supervisor return.',
            'version' => $block->version,
        ])->assertOk();
    }

    public function test_reporter_cannot_edit_block_after_forward_to_chief(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));
        $this->postJson("/api/slot-assignments/{$assignment->id}/forward", ['note' => 'Clean lane.'])->assertOk();

        $block = $this->block('1A', 'en');
        Sanctum::actingAs($this->user('RPT-001'));
        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Should not edit.',
            'version' => $block->version,
        ])->assertForbidden();
    }

    public function test_reporter_can_recommit_after_return(): void
    {
        $this->returnLane('1A', 'en', 'RPT-001', 'Please correct speaker attribution.');
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Sanctum::actingAs($this->user('RPT-001'));

        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'en'])
            ->assertOk()
            ->assertJsonPath('workflow_stage', 'supervisor');
    }

    public function test_recommit_after_return_writes_event_from_returned_to_supervisor(): void
    {
        $assignment = $this->returnLane('1A', 'en', 'RPT-001', 'Please correct speaker attribution.');
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Sanctum::actingAs($this->user('RPT-001'));

        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'en'])->assertOk();

        $this->assertDatabaseHas('slot_workflow_events', [
            'slot_assignment_id' => $assignment->id,
            'from_stage' => 'returned',
            'to_stage' => 'supervisor',
            'action' => 'commit',
        ]);
    }

    public function test_workflow_history_endpoint_returns_full_chain(): void
    {
        $assignment = $this->returnLane('1A', 'en', 'RPT-001', 'Please correct speaker attribution.');
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $response = $this->getJson("/api/slot-assignments/{$assignment->id}/history")->assertOk();

        $this->assertContains('commit', collect($response->json())->pluck('action'));
        $this->assertContains('return', collect($response->json())->pluck('action'));
    }

    public function test_audit_chain_intact_after_full_workflow_cycle(): void
    {
        $assignment = $this->returnLane('1A', 'en', 'RPT-001', 'Please correct speaker attribution.');
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Sanctum::actingAs($this->user('RPT-001'));
        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'en'])->assertOk();

        Sanctum::actingAs($this->user('SUP-EN-001'));
        $this->postJson("/api/slot-assignments/{$assignment->id}/forward", ['note' => 'Clean after correction.'])->assertOk();

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    private function commitLane(string $slotCode, string $lang, string $employeeId): SlotAssignment
    {
        $slot = Slot::query()->where('code', $slotCode)->firstOrFail();
        Sanctum::actingAs($this->user($employeeId));
        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => $lang])->assertOk();

        return SlotAssignment::query()
            ->where('slot_id', $slot->id)
            ->where('lang_role', $lang)
            ->firstOrFail();
    }

    private function returnLane(string $slotCode, string $lang, string $employeeId, string $reason): SlotAssignment
    {
        $assignment = $this->commitLane($slotCode, $lang, $employeeId);
        Sanctum::actingAs($this->user('SUP-EN-001'));
        $this->postJson("/api/slot-assignments/{$assignment->id}/return", ['reason' => $reason])->assertOk();

        return $assignment->fresh();
    }

    private function user(string $employeeId): User
    {
        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }

    private function block(string $slotCode, string $lang): Block
    {
        return Block::query()
            ->whereHas('slot', fn ($query) => $query->where('code', $slotCode))
            ->where('original_lang', $lang)
            ->orderBy('sequence')
            ->firstOrFail();
    }
}
