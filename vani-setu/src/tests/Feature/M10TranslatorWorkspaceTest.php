<?php

namespace Tests\Feature;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\User;
use App\Modules\Translator\Models\TranslatorAssignment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsAuditChains;
use Tests\TestCase;

class M10TranslatorWorkspaceTest extends TestCase
{
    use AssertsAuditChains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_translator_workspace_routes_render_the_authenticated_shell(): void
    {
        $assignment = $this->assignment();

        $this->get('/app/translator')
            ->assertOk()
            ->assertSee('role-workspace')
            ->assertSee('data-workspace="translator"', false);

        $this->get("/app/translator/assignments/{$assignment->id}")
            ->assertOk()
            ->assertSee('M10 Translator Workspace')
            ->assertSee('data-initial-assignment="'.$assignment->id.'"', false);
    }

    public function test_login_returns_translator_identity_and_queue_loads(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'employee_id' => 'TRN-EN-001',
            'password' => 'trans123',
        ])->assertOk()
            ->assertJsonPath('roles.0', 'translator');

        $this->withToken($login->json('token'))
            ->getJson('/api/translator/queue')
            ->assertOk()
            ->assertJsonFragment(['language_pair' => 'en_to_hi']);
    }

    public function test_non_translator_is_blocked_from_translator_api_contract(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $this->getJson('/api/translator/queue')->assertForbidden();
    }

    public function test_opening_assignment_loads_blocks_and_glossary(): void
    {
        $assignment = $this->assignment();
        Sanctum::actingAs($assignment->translator);

        $this->getJson("/api/translator/assignments/{$assignment->id}")
            ->assertOk()
            ->assertJsonPath('assignment.id', $assignment->id)
            ->assertJsonFragment(['language_pair' => 'en_to_hi']);
    }

    public function test_translation_edit_uses_version_and_conflict_payload(): void
    {
        $assignment = $this->assignment();
        $block = $assignment->blocks()->firstOrFail();
        Sanctum::actingAs($assignment->translator);

        $this->putJson("/api/translator/assignments/{$assignment->id}/blocks/{$block->id}", [
            'text' => 'M10 reviewed translation.',
            'version' => $block->version,
        ])->assertOk()
            ->assertJsonPath('translated_text', 'M10 reviewed translation.')
            ->assertJsonPath('version', $block->version + 1);

        $this->putJson("/api/translator/assignments/{$assignment->id}/blocks/{$block->id}", [
            'text' => 'Stale M10 translation.',
            'version' => $block->version,
        ])->assertStatus(409)
            ->assertJsonPath('message', 'Version conflict.');
    }

    public function test_ai_assist_accept_ai_and_glossary_are_available(): void
    {
        Http::fake([
            '*/v1/translate' => Http::response([
                'translation' => 'AI translated text.',
                'confidence' => 0.9,
                'model_version' => 'ui-test-model',
            ]),
        ]);

        $assignment = $this->assignment();
        Sanctum::actingAs($assignment->translator);

        $this->postJson("/api/translator/assignments/{$assignment->id}/request-ai")
            ->assertOk()
            ->assertJsonPath('assignment.status', 'in_review')
            ->assertJsonPath('assignment.ai_translation_meta.model_version', 'ui-test-model');

        $block = $assignment->blocks()->firstOrFail()->fresh();
        $this->postJson("/api/translator/assignments/{$assignment->id}/blocks/{$block->id}/accept-ai")
            ->assertOk()
            ->assertJsonPath('translated_text', $block->ai_text);

        $this->postJson('/api/translator/glossary', [
            'term_source' => 'M10 Term',
            'term_target' => 'M10 Target',
            'language_pair' => 'en_to_hi',
            'domain' => 'parliamentary',
            'notes' => 'Created from M10 workspace test.',
        ])->assertCreated()
            ->assertJsonPath('term_source', 'M10 Term');

        $this->assertAuditActionsChained('translator', [
            'translator.ai.requested',
            'translator.block.accept_ai',
            'translator.glossary.create',
        ]);
    }

    public function test_finalise_forward_and_sealed_read_only_state(): void
    {
        $assignment = $this->assignment();
        $block = $assignment->blocks()->firstOrFail();
        Sanctum::actingAs($assignment->translator);

        $updated = $this->putJson("/api/translator/assignments/{$assignment->id}/blocks/{$block->id}", [
            'text' => 'Hindi version ready.',
            'version' => $block->version,
        ])->assertOk()->json();

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/finalise", [
            'slot_version' => $updated['version'],
            'draft_type' => 'hv',
        ])->assertOk()
            ->assertJsonPath('assignment.status', 'hv_draft_finalised')
            ->assertJsonPath('next_stage', 'supervisor');

        $this->putJson("/api/translator/assignments/{$assignment->id}/blocks/{$block->id}", [
            'text' => 'Late edit.',
            'version' => $updated['version'],
        ])->assertStatus(423);

        $this->postJson("/api/translator/assignments/{$assignment->id}/forward-supervisor", [
            'note' => 'Ready for supervisor.',
        ])->assertOk()
            ->assertJsonPath('assignment.status', 'supervisor_review')
            ->assertJsonPath('assignment.ai_translation_meta.current_stage', 'supervisor');

        $this->assertAuditActionsChained('translator', [
            'translator.block.edit',
            'translator.draft.finalised',
            'translator.forward.supervisor',
        ]);
    }

    public function test_committed_assignment_is_read_only(): void
    {
        $assignment = $this->assignment();
        $block = $assignment->blocks()->firstOrFail();
        Sanctum::actingAs($assignment->translator);

        $this->postJson("/api/translator/assignments/{$assignment->id}/commit")
            ->assertOk()
            ->assertJsonPath('assignment.status', 'forwarded');

        $this->putJson("/api/translator/assignments/{$assignment->id}/blocks/{$block->id}", [
            'text' => 'Should not save.',
            'version' => $block->version,
        ])->assertStatus(423);
    }

    private function assignment(): TranslatorAssignment
    {
        return TranslatorAssignment::query()->where('language_pair', 'en_to_hi')->firstOrFail();
    }
}
