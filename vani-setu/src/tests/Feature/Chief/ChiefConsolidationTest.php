<?php

namespace Tests\Feature\Chief;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChiefConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->moveWindowToChief('A');
    }

    public function test_queue_visibility_is_scoped_to_uncommitted_actor_lane(): void
    {
        Sanctum::actingAs($this->user('CHF-EN-001'));

        $this->getJson('/api/chief/queue')
            ->assertOk()
            ->assertJsonFragment(['window_code' => 'A']);

        $this->postJson('/api/chief/consolidations/'.$this->consolidation('A')->id.'/commit', ['lang_side' => 'en'])
            ->assertOk();

        $this->getJson('/api/chief/queue')
            ->assertOk()
            ->assertJsonMissing(['window_code' => 'A']);

        Sanctum::actingAs($this->user('CHF-HI-001'));

        $this->getJson('/api/chief/queue')
            ->assertOk()
            ->assertJsonFragment(['window_code' => 'A']);
    }

    public function test_chief_can_only_edit_blocks_in_their_lane(): void
    {
        $consolidation = $this->consolidation('A');
        $enBlock = $this->block('A', 'en');
        $hiBlock = $this->block('A', 'hi');
        Sanctum::actingAs($this->user('CHF-EN-001'));

        $this->putJson("/api/chief/consolidations/{$consolidation->id}/blocks/{$hiBlock->id}", [
            'text' => 'This edit should be rejected.',
            'version' => $hiBlock->version,
        ])->assertForbidden();

        $this->putJson("/api/chief/consolidations/{$consolidation->id}/blocks/{$enBlock->id}", [
            'text' => 'Chief corrected English text.',
            'version' => $enBlock->version,
        ])
            ->assertOk()
            ->assertJsonPath('text', 'Chief corrected English text.');

        $this->assertDatabaseHas('chief_edits', [
            'consolidation_id' => $consolidation->id,
            'block_id' => $enBlock->id,
            'kind' => 'text',
            'after' => 'Chief corrected English text.',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'chief.block.edit']);
    }

    public function test_speaker_override_writes_audit_chain(): void
    {
        $consolidation = $this->consolidation('A');
        $block = $this->block('A', 'en');
        $member = Member::query()->where('roster_id', 'R034')->firstOrFail();
        Sanctum::actingAs($this->user('CHF-EN-001'));

        $this->putJson("/api/chief/consolidations/{$consolidation->id}/blocks/{$block->id}/speaker", [
            'member_id' => $member->id,
        ])->assertOk();

        $this->assertDatabaseHas('chief_speaker_overrides', [
            'consolidation_id' => $consolidation->id,
            'block_id' => $block->id,
            'chief_member_id' => $member->id,
        ]);
        $this->assertDatabaseHas('chief_edits', [
            'consolidation_id' => $consolidation->id,
            'block_id' => $block->id,
            'kind' => 'speaker',
            'after' => $member->name_en,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'chief.block.speaker']);

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    public function test_dual_side_commit_transitions_consolidation(): void
    {
        $consolidation = $this->consolidation('A');

        Sanctum::actingAs($this->user('CHF-EN-001'));
        $this->postJson("/api/chief/consolidations/{$consolidation->id}/commit", ['lang_side' => 'en'])
            ->assertOk()
            ->assertJsonPath('consolidation.status', 'en_committed');

        Sanctum::actingAs($this->user('CHF-HI-001'));
        $this->postJson("/api/chief/consolidations/{$consolidation->id}/commit", ['lang_side' => 'hi'])
            ->assertOk()
            ->assertJsonPath('consolidation.status', 'dual_committed');

        $this->assertTrue($consolidation->fresh()->bothCommitted());
        $this->assertDatabaseHas('audit_logs', ['action' => 'chief.consolidation.commit']);
    }

    public function test_return_logic_moves_window_assignments_back_and_records_workflow_events(): void
    {
        $consolidation = $this->consolidation('A');
        Sanctum::actingAs($this->user('CHF-EN-001'));

        $this->postJson("/api/chief/consolidations/{$consolidation->id}/return", [
            'reason' => 'Speaker attribution needs supervisor review.',
            'to_stage' => 'supervisor',
        ])
            ->assertOk()
            ->assertJsonPath('consolidation.status', 'open');

        $this->assertDatabaseHas('slot_workflow_events', [
            'from_stage' => 'chief',
            'to_stage' => 'supervisor',
            'action' => 'return',
            'reason' => 'Speaker attribution needs supervisor review.',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'chief.consolidation.return']);
        $this->assertSame(0, SlotAssignment::query()
            ->whereIn('slot_id', $consolidation->slotIdsInWindow())
            ->where('workflow_stage', 'chief')
            ->count());
    }

    public function test_return_reason_requires_at_least_ten_characters(): void
    {
        Sanctum::actingAs($this->user('CHF-EN-001'));

        $this->postJson('/api/chief/consolidations/'.$this->consolidation('A')->id.'/return', [
            'reason' => 'short',
            'to_stage' => 'reporter',
        ])->assertStatus(422);
    }

    public function test_audit_chain_still_verifies_after_chief_workflow(): void
    {
        $consolidation = $this->consolidation('A');
        $block = $this->block('A', 'en');
        Sanctum::actingAs($this->user('CHF-EN-001'));

        $this->putJson("/api/chief/consolidations/{$consolidation->id}/blocks/{$block->id}", [
            'text' => 'Chief audit verification edit.',
            'version' => $block->version,
        ])->assertOk();
        $this->postJson("/api/chief/consolidations/{$consolidation->id}/commit", ['lang_side' => 'en'])->assertOk();

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    private function moveWindowToChief(string $windowCode): void
    {
        $consolidation = $this->consolidation($windowCode);

        SlotAssignment::query()
            ->whereIn('slot_id', $consolidation->slotIdsInWindow())
            ->update([
                'status' => 'committed',
                'workflow_stage' => 'chief',
                'last_workflow_action_at' => now(),
            ]);
    }

    private function consolidation(string $windowCode): ChiefConsolidation
    {
        return ChiefConsolidation::query()->where('window_code', $windowCode)->firstOrFail();
    }

    private function block(string $windowCode, string $chiefLang): Block
    {
        $consolidation = $this->consolidation($windowCode);

        return Block::query()
            ->where('chief_lang', $chiefLang)
            ->whereHas('slot', fn ($query) => $query->whereIn('id', $consolidation->slotIdsInWindow()))
            ->orderBy('slot_id')
            ->orderBy('sequence')
            ->firstOrFail();
    }

    private function user(string $employeeId): User
    {
        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
