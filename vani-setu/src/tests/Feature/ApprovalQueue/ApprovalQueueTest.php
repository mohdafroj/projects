<?php

namespace Tests\Feature\ApprovalQueue;

use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\ModuleTestCase;

class ApprovalQueueTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedModuleBase();
    }

    public function test_reporter_sees_only_their_pending_capture_assignments(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        Sanctum::actingAs($reporter);

        $response = $this->getJson('/api/approval-queue/items')
            ->assertOk()
            ->assertJsonPath('summary.by_module.capture', 2);

        $keys = collect($response->json('items'))->pluck('key');
        $this->assertCount(2, $keys);
        $this->assertTrue($keys->every(fn (string $key) => str_starts_with($key, 'capture.slot_assignment.')));
    }

    public function test_supervisor_queue_includes_language_stage_items(): void
    {
        $supervisor = User::query()->where('employee_id', 'SUP-EN-001')->firstOrFail();
        $assignment = SlotAssignment::query()
            ->where('lang_role', 'en')
            ->orderBy('id')
            ->firstOrFail();
        $assignment->forceFill([
            'status' => 'committed',
            'workflow_stage' => 'supervisor',
            'last_workflow_action_at' => now(),
        ])->save();

        Sanctum::actingAs($supervisor);

        $this->getJson('/api/approval-queue/items?module=capture')
            ->assertOk()
            ->assertJsonFragment([
                'key' => "capture.slot_assignment.{$assignment->id}",
                'status' => 'supervisor',
                'action_label' => 'Review supervisor queue',
            ]);
    }

    public function test_acknowledge_hides_item_and_writes_approval_queue_audit(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        Sanctum::actingAs($reporter);
        $assignment = SlotAssignment::query()
            ->where('user_id', $reporter->id)
            ->where('workflow_stage', 'reporter')
            ->orderBy('id')
            ->firstOrFail();
        $itemKey = "capture.slot_assignment.{$assignment->id}";

        $this->postJson("/api/approval-queue/items/{$itemKey}/acknowledge", [
            'note' => 'Handled outside the queue.',
        ])
            ->assertOk()
            ->assertJsonPath('queue_action.action', 'acknowledged');

        $this->getJson('/api/approval-queue/items')
            ->assertOk()
            ->assertJsonMissing(['key' => $itemKey]);

        $this->getJson('/api/approval-queue/items?include_acknowledged=1')
            ->assertOk()
            ->assertJsonFragment(['key' => $itemKey]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'approval_queue.acknowledged']);
        $this->assertDatabaseHas('approval_queue_actions', [
            'user_id' => $reporter->id,
            'item_key' => $itemKey,
            'action' => 'acknowledged',
        ]);
    }
}
