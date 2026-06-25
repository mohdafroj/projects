<?php

namespace Tests\Feature\WorkflowBoard;

use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_board_groups_live_assignments_by_workflow_stage(): void
    {
        Sanctum::actingAs($this->adminUser());

        $assignment = SlotAssignment::query()->where('workflow_stage', 'reporter')->orderBy('id')->firstOrFail();

        $this->getJson('/api/workflow-board/assignments')
            ->assertOk()
            ->assertJsonFragment(['id' => $assignment->id])
            ->assertJsonStructure([
                'stages' => ['reporter', 'returned', 'supervisor', 'chief'],
                'counts' => ['reporter', 'returned', 'supervisor', 'chief'],
            ]);
    }

    public function test_supervisor_can_forward_language_assignment_to_chief_with_audit_event(): void
    {
        $supervisor = User::query()->where('employee_id', 'SUP-EN-001')->firstOrFail();
        Sanctum::actingAs($supervisor);

        $assignment = $this->supervisorAssignment();

        $this->postJson("/api/workflow-board/assignments/{$assignment->id}/transition", [
            'to_stage' => 'chief',
            'reason' => 'Supervisor pass complete.',
        ])
            ->assertOk()
            ->assertJsonPath('assignment.workflow_stage', 'chief');

        $this->assertDatabaseHas('audit_logs', ['action' => 'workflow_board.forward']);
        $this->assertDatabaseHas('slot_workflow_events', [
            'slot_assignment_id' => $assignment->id,
            'from_stage' => 'supervisor',
            'to_stage' => 'chief',
            'action' => 'forward',
            'actor_id' => $supervisor->id,
        ]);
    }

    public function test_wrong_role_cannot_drag_assignment_between_stages(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $assignment = $this->supervisorAssignment();

        $this->postJson("/api/workflow-board/assignments/{$assignment->id}/transition", [
            'to_stage' => 'chief',
            'reason' => 'Reporter should not move this.',
        ])
            ->assertForbidden()
            ->assertJsonPath('current_stage', 'supervisor');

        $this->assertSame('supervisor', $assignment->fresh()->workflow_stage);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'workflow_board.forward']);
    }

    private function adminUser(): User
    {
        return User::query()->where('employee_id', 'ADM-001')->firstOrFail();
    }

    private function supervisorAssignment(): SlotAssignment
    {
        $assignment = SlotAssignment::query()
            ->where('lang_role', 'en')
            ->orderBy('id')
            ->firstOrFail();

        $assignment->forceFill([
            'status' => 'committed',
            'workflow_stage' => 'supervisor',
            'last_workflow_action_at' => now(),
        ])->save();

        return $assignment->fresh();
    }
}
