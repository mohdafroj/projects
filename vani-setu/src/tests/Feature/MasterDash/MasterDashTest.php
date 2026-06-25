<?php

namespace Tests\Feature\MasterDash;

use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_overview_returns_cross_role_operational_summary_and_audits_access(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $slot = $this->pendingSlot($reporter);
        Sanctum::actingAs($reporter);

        $response = $this->getJson('/api/master-dash/overview')
            ->assertOk()
            ->assertJsonPath('actor.employee_id', 'RPT-001')
            ->assertJsonFragment(['role' => 'reporter']);

        $this->assertGreaterThanOrEqual(1, $response->json('sittings.live'));
        $this->assertGreaterThanOrEqual(1, $response->json('workflow.assignments_by_stage.reporter'));
        $this->assertGreaterThanOrEqual(1, $response->json('pendency.total'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'master_dash.overview.view']);
        $this->assertDatabaseHas('slot_assignments', ['slot_id' => $slot->id, 'workflow_stage' => 'reporter']);
    }

    public function test_pendency_can_be_filtered_by_workflow_stage(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $supervisor = User::query()->where('employee_id', 'SUP-EN-001')->firstOrFail();
        $reporterSlot = $this->pendingSlot($reporter, 'reporter');
        $supervisorSlot = $this->pendingSlot($supervisor, 'supervisor', 'P2');
        Sanctum::actingAs(User::query()->where('employee_id', 'ADM-001')->firstOrFail());

        $response = $this->getJson('/api/master-dash/pendency?stage=supervisor')
            ->assertOk()
            ->assertJsonPath('items.0.slot_id', $supervisorSlot->id);

        $this->assertNotContains($reporterSlot->id, collect($response->json('items'))->pluck('slot_id')->all());
        $this->assertGreaterThanOrEqual(1, $response->json('summary.by_stage.reporter'));
        $this->assertGreaterThanOrEqual(1, $response->json('summary.by_stage.supervisor'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'master_dash.pendency.view']);
    }

    public function test_roster_lists_users_and_role_counts(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'DIR-001')->firstOrFail());

        $this->getJson('/api/master-dash/roster')
            ->assertOk()
            ->assertJsonFragment(['employee_id' => 'DIR-001'])
            ->assertJsonFragment(['role' => 'director'])
            ->assertJsonFragment(['role' => 'admin']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'master_dash.roster.view']);
    }

    private function pendingSlot(User $user, string $stage = 'reporter', string $code = 'P1'): Slot
    {
        $sitting = Sitting::query()->firstOrCreate(
            ['session_no' => 2026, 'sitting_no' => 77],
            ['sitting_date' => '2026-05-20', 'status' => 'live'],
        );

        $slot = Slot::query()->create([
            'sitting_id' => $sitting->id,
            'code' => $code,
            'start_offset_ms' => $code === 'P1' ? 0 : 300000,
            'duration_ms' => 300000,
            'topic' => 'Question Hour',
            'status' => 'in_progress',
        ]);

        SlotAssignment::query()->create([
            'slot_id' => $slot->id,
            'user_id' => $user->id,
            'assignee_user_id' => $user->id,
            'lang_role' => 'en',
            'status' => 'open',
            'workflow_stage' => $stage,
            'last_workflow_action_at' => now()->subMinutes(15),
        ]);

        return $slot;
    }
}
