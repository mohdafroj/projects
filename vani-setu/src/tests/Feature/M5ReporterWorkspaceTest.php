<?php

namespace Tests\Feature;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsAuditChains;
use Tests\TestCase;

class M5ReporterWorkspaceTest extends TestCase
{
    use AssertsAuditChains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('REPORTER_AUDIO_MOCK=true');
        $_ENV['REPORTER_AUDIO_MOCK'] = 'true';

        $this->seed(DatabaseSeeder::class);
    }

    public function test_workspace_routes_render_the_authenticated_shell(): void
    {
        $this->get('/app/login')
            ->assertOk()
            ->assertSee('role-workspace')
            ->assertSee('data-workspace="router"', false)
            ->assertSee('Vani Setu App Login');

        $slot = Slot::query()->where('code', '1A')->firstOrFail();

        $this->get("/app/reporter/slots/{$slot->id}")
            ->assertOk()
            ->assertSee('M5 Reporter Workspace')
            ->assertSee('data-initial-slot="'.$slot->id.'"', false);
    }

    public function test_login_returns_reporter_identity_and_assignments_load(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'employee_id' => 'RPT-001',
            'password' => 'reporter123',
        ])->assertOk()
            ->assertJsonPath('roles.0', 'reporter');

        $this->withToken($login->json('token'))
            ->getJson('/api/me/assignments')
            ->assertOk()
            ->assertJsonFragment(['code' => '1A'])
            ->assertJsonFragment(['code' => '1F']);
    }

    public function test_non_reporter_identity_is_not_authorised_for_reporter_ui_actions(): void
    {
        $admin = User::query()->where('employee_id', 'ADM-001')->firstOrFail();
        $block = $this->block('1A', 'en');
        Sanctum::actingAs($admin);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonMissing(['roles' => ['reporter']]);

        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Admin should not use the reporter lane.',
            'version' => $block->version,
        ])->assertForbidden();
    }

    public function test_selecting_assignment_loads_slot_blocks_for_the_reporter_lane(): void
    {
        Sanctum::actingAs($this->user('RPT-001'));
        $assignment = SlotAssignment::query()
            ->whereHas('slot', fn ($query) => $query->where('code', '1A'))
            ->where('lang_role', 'en')
            ->firstOrFail();

        $response = $this->getJson("/api/slots/{$assignment->slot_id}")
            ->assertOk()
            ->assertJsonPath('code', '1A');

        $laneBlocks = collect($response->json('blocks'))->where('original_lang', 'en');
        $this->assertGreaterThan(0, $laneBlocks->count());
    }

    public function test_block_edit_uses_version_and_conflict_response_supports_resolve_state(): void
    {
        $block = $this->block('1A', 'en');
        Sanctum::actingAs($this->user('RPT-001'));

        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'M5 workspace draft edit.',
            'version' => $block->version,
        ])->assertOk()
            ->assertJsonPath('version', $block->version + 1)
            ->assertJsonPath('text', 'M5 workspace draft edit.');

        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Stale M5 edit.',
            'version' => $block->version,
        ])->assertStatus(409)
            ->assertJsonPath('current_version', $block->version + 1)
            ->assertJsonPath('current_text', 'M5 workspace draft edit.');
    }

    public function test_speaker_edit_and_submit_make_assignment_read_only(): void
    {
        $block = $this->block('1A', 'en');
        $memberId = \App\Modules\Core\Models\Member::query()->where('roster_id', 'R034')->value('id');
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Sanctum::actingAs($this->user('RPT-001'));

        $this->putJson("/api/blocks/{$block->id}/speaker", [
            'member_id' => $memberId,
        ])->assertOk()
            ->assertJsonPath('member_id', $memberId);

        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'en'])
            ->assertOk()
            ->assertJsonPath('status', 'committed')
            ->assertJsonPath('workflow_stage', 'supervisor');

        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Post-submit edit should be blocked.',
            'version' => $block->fresh()->version,
        ])->assertForbidden();

        $this->assertAuditActionsChained('speech_to_text', [
            'capture.block.speaker',
            'capture.slot.commit',
        ]);
    }

    public function test_audio_close_uses_existing_reporter_capture_api_when_available(): void
    {
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Sanctum::actingAs($this->user('RPT-001'));

        $this->postJson("/api/reporter/slot/{$slot->id}/audio-close")
            ->assertOk()
            ->assertJsonPath('slot_id', $slot->id)
            ->assertJsonPath('closed', true)
            ->assertJsonPath('mock', true);
    }

    public function test_returned_assignment_shows_latest_supervisor_context_and_can_resubmit(): void
    {
        $assignment = $this->commitLane('1A', 'en', 'RPT-001');
        Sanctum::actingAs($this->user('SUP-EN-001'));
        $this->postJson("/api/slot-assignments/{$assignment->id}/return", [
            'reason' => 'Please correct speaker attribution.',
        ])->assertOk();

        Sanctum::actingAs($this->user('RPT-001'));
        $this->getJson('/api/me/assignments')
            ->assertOk()
            ->assertJsonFragment(['workflow_stage' => 'returned'])
            ->assertJsonFragment(['reason' => 'Please correct speaker attribution.']);

        $this->postJson("/api/slots/{$assignment->slot_id}/commit", ['lang_role' => 'en'])
            ->assertOk()
            ->assertJsonPath('workflow_stage', 'supervisor');
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
