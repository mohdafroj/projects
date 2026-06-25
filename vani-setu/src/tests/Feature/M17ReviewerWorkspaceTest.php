<?php

namespace Tests\Feature;

use App\Modules\Core\Models\User;
use App\Modules\Translator\Models\TranslatorAssignment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsAuditChains;
use Tests\TestCase;

class M17ReviewerWorkspaceTest extends TestCase
{
    use AssertsAuditChains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_reviewer_workspace_routes_render_shell(): void
    {
        $assignment = $this->markSupervisorReviewAssignmentDirectly();

        $this->get('/app/reviewer')
            ->assertOk()
            ->assertSee('data-workspace="reviewer"', false)
            ->assertSee('M17 Reviewer Workspace');

        $this->get("/app/reviewer/assignments/{$assignment->id}")
            ->assertOk()
            ->assertSee('data-initial-assignment="'.$assignment->id.'"', false);
    }

    public function test_supervisor_login_and_reviewer_queue_loads(): void
    {
        $assignment = $this->markSupervisorReviewAssignmentDirectly();

        $login = $this->postJson('/api/auth/login', [
            'employee_id' => 'SUP-EN-001',
            'password' => 'sup123',
        ])->assertOk()
            ->assertJsonPath('roles.0', 'supervisor');

        $this->withToken($login->json('token'))
            ->getJson('/api/translator/reviewer/queue')
            ->assertOk()
            ->assertJsonFragment(['id' => $assignment->id, 'status' => 'supervisor_review']);
    }

    public function test_non_reviewer_cannot_access_reviewer_queue(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'TRN-EN-001')->firstOrFail());

        $this->getJson('/api/translator/reviewer/queue')->assertForbidden();
    }

    public function test_reviewer_can_open_assignment_detail_with_history(): void
    {
        $assignment = $this->supervisorReviewAssignment();
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->getJson("/api/translator/reviewer/assignments/{$assignment->id}")
            ->assertOk()
            ->assertJsonPath('assignment.id', $assignment->id)
            ->assertJsonPath('assignment.status', 'supervisor_review')
            ->assertJsonStructure(['blocks', 'glossary', 'history' => ['edits', 'audit']]);
    }

    public function test_reviewer_can_return_assignment_to_translator_with_audit(): void
    {
        $assignment = $this->supervisorReviewAssignment();
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->postJson("/api/translator/reviewer/assignments/{$assignment->id}/return-translator", [
            'reason' => 'Please correct terminology before director handoff.',
        ])->assertOk()
            ->assertJsonPath('assignment.status', 'returned')
            ->assertJsonPath('assignment.ai_translation_meta.return_reason', 'Please correct terminology before director handoff.');

        $this->assertAuditActionsChained('translator', [
            'translator.reviewer.return',
        ]);
    }

    public function test_reviewer_can_forward_assignment_to_director(): void
    {
        $assignment = $this->supervisorReviewAssignment();
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->postJson("/api/translator/reviewer/assignments/{$assignment->id}/forward-director", [
            'note' => 'Reviewer cleared.',
        ])->assertOk()
            ->assertJsonPath('assignment.status', 'director_review')
            ->assertJsonPath('assignment.ai_translation_meta.current_stage', 'director');

        $this->assertAuditActionsChained('translator', [
            'translator.draft.finalised',
            'translator.forward.supervisor',
            'translator.forward.director',
        ]);
    }

    public function test_reviewer_language_competency_scopes_queue(): void
    {
        $assignment = $this->supervisorReviewAssignment();
        $assignment->forceFill(['language_pair' => 'ta_to_mr'])->save();
        Sanctum::actingAs($this->user('SUP-EN-001'));

        $this->getJson('/api/translator/reviewer/queue')
            ->assertOk()
            ->assertJsonMissing(['id' => $assignment->id]);
    }

    private function supervisorReviewAssignment(): TranslatorAssignment
    {
        $assignment = TranslatorAssignment::query()->where('language_pair', 'en_to_hi')->firstOrFail();
        $block = $assignment->blocks()->firstOrFail();
        $translator = $assignment->translator;

        Sanctum::actingAs($translator);
        $updated = $this->putJson("/api/translator/assignments/{$assignment->id}/blocks/{$block->id}", [
            'text' => 'Hindi version ready for reviewer.',
            'version' => $block->version,
        ])->assertOk()->json();

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/finalise", [
            'slot_version' => $updated['version'],
            'draft_type' => 'hv',
        ])->assertOk();

        $this->postJson("/api/translator/assignments/{$assignment->id}/forward-supervisor", [
            'note' => 'Ready for supervisor.',
        ])->assertOk();

        return $assignment->fresh();
    }

    private function markSupervisorReviewAssignmentDirectly(): TranslatorAssignment
    {
        $assignment = TranslatorAssignment::query()->where('language_pair', 'en_to_hi')->firstOrFail();
        $assignment->forceFill([
            'status' => 'supervisor_review',
            'ai_translation_meta' => [
                'current_stage' => 'supervisor',
                'supervisor_handoff' => ['note' => 'Ready for supervisor.'],
            ],
        ])->save();

        return $assignment->fresh();
    }

    private function user(string $employeeId): User
    {
        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
