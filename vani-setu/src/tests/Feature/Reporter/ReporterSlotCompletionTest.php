<?php

namespace Tests\Feature\Reporter;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReporterSlotCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_can_reassign_reporter_slot_with_audit(): void
    {
        $supervisor = User::query()->where('employee_id', 'SUP-EN-001')->firstOrFail();
        $newReporter = User::query()->where('employee_id', 'RPT-006')->firstOrFail();
        $assignment = $this->assignment('1A', 'en');
        Sanctum::actingAs($supervisor);

        $this->postJson("/api/slot-assignments/{$assignment->id}/reassign", [
            'user_id' => $newReporter->id,
            'reason' => 'Network failure requires supervisor override.',
        ])->assertOk()
            ->assertJsonPath('assignment.user_id', $newReporter->id)
            ->assertJsonPath('assignment.workflow_stage', 'reporter');

        $this->assertDatabaseHas('audit_logs', ['action' => 'reporter.slot.reassigned']);
        $this->assertDatabaseHas('slot_workflow_events', [
            'slot_assignment_id' => $assignment->id,
            'action' => 'return',
        ]);
    }

    public function test_reassignment_requires_reporter_language_competency(): void
    {
        $supervisor = User::query()->where('employee_id', 'SUP-HI-001')->firstOrFail();
        $englishOnlyReporter = User::query()->where('employee_id', 'RPT-006')->firstOrFail();
        $assignment = $this->assignment('1A', 'hi');
        Sanctum::actingAs($supervisor);

        $this->postJson("/api/slot-assignments/{$assignment->id}/reassign", [
            'user_id' => $englishOnlyReporter->id,
            'reason' => 'Supervisor override after reporter connectivity failure.',
        ])->assertUnprocessable();
    }

    public function test_reporter_recovery_detects_missing_audio_chunks(): void
    {
        Storage::fake('vani_audio');
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Storage::disk('vani_audio')->put("reporter-audio/{$slot->id}/chunk-1.webm", 'one');
        Storage::disk('vani_audio')->put("reporter-audio/{$slot->id}/chunk-3.webm", 'three');
        Sanctum::actingAs($reporter);

        $this->postJson("/api/reporter/slot/{$slot->id}/recovery", [
            'reason' => 'Dropped network while uploading chunk two.',
        ])->assertOk()
            ->assertJsonPath('partial', true)
            ->assertJsonPath('missing_sequences.0', 2);

        $this->assertDatabaseHas('audit_logs', ['action' => 'reporter.slot.recovery.inspected']);
    }

    public function test_recovery_marks_complete_when_chunks_are_contiguous(): void
    {
        Storage::fake('vani_audio');
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Storage::disk('vani_audio')->put("reporter-audio/{$slot->id}/chunk-1.webm", 'one');
        Storage::disk('vani_audio')->put("reporter-audio/{$slot->id}/chunk-2.webm", 'two');
        Sanctum::actingAs($reporter);

        $this->postJson("/api/reporter/slot/{$slot->id}/recovery", [
            'reason' => 'Resume after browser reconnect.',
        ])->assertOk()
            ->assertJsonPath('complete', true)
            ->assertJsonPath('missing_sequences', []);
    }

    public function test_audit_sweep_counts_reporter_edits(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $supervisor = User::query()->where('employee_id', 'SUP-EN-001')->firstOrFail();
        $block = $this->block('1A', 'en');

        Sanctum::actingAs($reporter);
        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Reporter audit sweep edit.',
            'version' => $block->version,
        ])->assertOk();

        Sanctum::actingAs($supervisor);
        $this->postJson("/api/reporter/slot/{$block->slot_id}/audit-sweep")
            ->assertOk()
            ->assertJsonPath('edited_blocks', 1)
            ->assertJsonPath('complete', true);
    }

    public function test_audit_sweep_recovers_missing_reporter_edit_audit_entries(): void
    {
        $supervisor = User::query()->where('employee_id', 'SUP-EN-001')->firstOrFail();
        $block = $this->block('1A', 'en');
        $block->forceFill([
            'text' => 'Edited before audit recovery.',
            'version' => $block->version + 1,
            'reporter_edit_count' => 1,
        ])->save();
        Sanctum::actingAs($supervisor);

        $this->postJson("/api/reporter/slot/{$block->slot_id}/audit-sweep")
            ->assertOk()
            ->assertJsonPath('missing_block_ids.0', $block->id)
            ->assertJsonPath('complete', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'reporter.slot.audit_sweep.recovered_edit']);
    }

    public function test_unification_preview_reports_overlapping_blocks(): void
    {
        $supervisor = User::query()->where('employee_id', 'SUP-EN-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        $second = $slot->blocks()->orderBy('sequence')->skip(1)->firstOrFail();
        $second->forceFill(['start_ms' => 1000])->save();
        Sanctum::actingAs($supervisor);

        $this->getJson("/api/reporter/slot/{$slot->id}/unification-preview")
            ->assertOk()
            ->assertJsonCount(1, 'overlaps');
    }

    public function test_unification_preview_reports_gaps_between_blocks(): void
    {
        $supervisor = User::query()->where('employee_id', 'SUP-EN-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1D')->firstOrFail();
        $second = $slot->blocks()->orderBy('sequence')->skip(1)->firstOrFail();
        $second->forceFill(['start_ms' => 200000])->save();
        Sanctum::actingAs($supervisor);

        $this->getJson("/api/reporter/slot/{$slot->id}/unification-preview")
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('gaps.0.gap_ms', 123500)->etc());
    }

    public function test_supervisor_can_finalise_flexible_slot_duration(): void
    {
        $supervisor = User::query()->where('employee_id', 'SUP-EN-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Sanctum::actingAs($supervisor);

        $this->patchJson("/api/reporter/slot/{$slot->id}/duration", [
            'duration_ms' => 420000,
            'reason' => 'Slot overran due extended minister reply.',
        ])->assertOk()
            ->assertJsonPath('slot.duration_ms', 420000);

        $this->assertDatabaseHas('audit_logs', ['action' => 'reporter.slot.duration_finalised']);
    }

    private function assignment(string $slotCode, string $lang): SlotAssignment
    {
        return SlotAssignment::query()
            ->whereHas('slot', fn ($query) => $query->where('code', $slotCode))
            ->where('lang_role', $lang)
            ->firstOrFail();
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
