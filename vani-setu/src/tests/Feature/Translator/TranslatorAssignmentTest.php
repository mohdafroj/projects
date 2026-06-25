<?php

namespace Tests\Feature\Translator;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use App\Modules\Translator\Models\TranslatorAssignment;
use App\Modules\Translator\Models\TranslatorGlossary;
use App\Modules\Translator\Seeders\DemoTranslatorAssignmentSeeder;
use App\Modules\Translator\Seeders\TranslatorGlossarySeeder;
use App\Modules\Translator\Seeders\TranslatorUsersSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\ModuleTestCase;

class TranslatorAssignmentTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedModuleBase();
        $this->seed([
            TranslatorUsersSeeder::class,
            TranslatorGlossarySeeder::class,
            DemoTranslatorAssignmentSeeder::class,
        ]);
    }

    public function test_queue_visibility_per_language_pair(): void
    {
        Sanctum::actingAs($this->translator('TRN-EN-001'));

        $this->getJson('/api/translator/queue')
            ->assertOk()
            ->assertJsonFragment(['language_pair' => 'en_to_hi'])
            ->assertJsonMissing(['language_pair' => 'hi_to_en']);
    }

    public function test_ai_assist_writes_meta_and_audit_and_enforces_terms(): void
    {
        Http::fake([
            '*/v1/translate' => Http::response([
                'translation' => 'Treasury Benches and PM-KISAN Scheme',
                'confidence' => 0.9,
                'model_version' => 'indictrans2-test',
            ]),
        ]);

        $assignment = $this->assignment();
        Sanctum::actingAs($assignment->translator);

        $this->postJson("/api/translator/assignments/{$assignment->id}/request-ai")
            ->assertOk()
            ->assertJsonPath('assignment.status', 'in_review')
            ->assertJsonPath('assignment.ai_translation_meta.model_version', 'indictrans2-test');

        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.ai.requested']);
        $this->assertStringContainsString('सत्ता पक्ष की बेंचें', (string) $assignment->blocks()->first()->fresh()->ai_text);
    }

    public function test_edit_policy_by_language_pair_and_optimistic_lock(): void
    {
        $assignment = $this->assignment();
        $block = $assignment->blocks()->firstOrFail();
        Sanctum::actingAs($assignment->translator);

        $this->putJson("/api/translator/assignments/{$assignment->id}/blocks/{$block->id}", [
            'text' => 'Translator edited text.',
            'version' => $block->version,
        ])->assertOk()
            ->assertJsonPath('translated_text', 'Translator edited text.');

        $this->putJson("/api/translator/assignments/{$assignment->id}/blocks/{$block->id}", [
            'text' => 'Stale edit.',
            'version' => $block->version,
        ])->assertStatus(409);

        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.block.edit']);
    }

    public function test_accept_ai_writes_audit_action(): void
    {
        $assignment = $this->assignment();
        $block = $assignment->blocks()->firstOrFail();
        $block->forceFill(['ai_text' => 'Accepted AI text.'])->save();
        Sanctum::actingAs($assignment->translator);

        $this->postJson("/api/translator/assignments/{$assignment->id}/blocks/{$block->id}/accept-ai")
            ->assertOk()
            ->assertJsonPath('translated_text', 'Accepted AI text.');

        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.block.accept_ai']);
    }

    public function test_commit_transitions_and_return_audit_logs(): void
    {
        $assignment = $this->assignment();
        Sanctum::actingAs($assignment->translator);

        $this->postJson("/api/translator/assignments/{$assignment->id}/commit")
            ->assertOk()
            ->assertJsonPath('assignment.status', 'forwarded');

        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.assignment.commit']);

        $this->postJson("/api/translator/assignments/{$assignment->id}/return", [
            'reason' => 'Reviewer requested terminology correction.',
            'to_stage' => 'translator',
        ])->assertOk()
            ->assertJsonPath('status', 'returned');

        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.assignment.return']);
    }

    public function test_slot_draft_load_patch_three_times_and_commit_with_audit(): void
    {
        $assignment = $this->assignment();
        $block = $assignment->blocks()->firstOrFail();
        Sanctum::actingAs($assignment->translator);

        $draft = $this->getJson("/api/translator/slot/{$assignment->slot_id}/draft")
            ->assertOk()
            ->assertJsonPath('review_mode', 'single_editor')
            ->assertJsonPath('collaboration_required', false)
            ->json();

        $version = $draft['slot_version'];
        foreach (['First review edit.', 'Second review edit.', 'Final review edit.'] as $text) {
            $draft = $this->patchJson("/api/translator/slot/{$assignment->slot_id}/draft", [
                'slot_version' => $version,
                'edits' => [
                    ['block_id' => $block->id, 'text' => $text],
                ],
            ])->assertOk()
                ->assertJsonPath('blocks.0.translated_text', $text)
                ->json();
            $version = $draft['slot_version'];
        }

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/commit")
            ->assertOk()
            ->assertJsonPath('assignment.status', 'forwarded');

        $this->assertSame('Final review edit.', $block->fresh()->translated_text);
        $this->assertSame(3, $assignment->edits()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.slot_draft.patch']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.slot_draft.commit']);
    }

    public function test_slot_draft_version_changes_when_non_max_block_is_patched(): void
    {
        $assignment = $this->assignment();
        $blocks = $assignment->blocks()->get()->values();
        $blocks[0]->forceFill(['version' => 4])->save();
        $blocks[1]->forceFill(['version' => 1])->save();
        Sanctum::actingAs($assignment->translator);

        $draft = $this->getJson("/api/translator/slot/{$assignment->slot_id}/draft")
            ->assertOk()
            ->assertJsonPath('slot_version', 4)
            ->json();

        $updatedDraft = $this->patchJson("/api/translator/slot/{$assignment->slot_id}/draft", [
            'slot_version' => $draft['slot_version'],
            'edits' => [
                ['block_id' => $blocks[1]->id, 'text' => 'Lower-version block edit.'],
            ],
        ])->assertOk()
            ->assertJsonPath('slot_version', 5)
            ->json();

        $this->patchJson("/api/translator/slot/{$assignment->slot_id}/draft", [
            'slot_version' => $draft['slot_version'],
            'edits' => [
                ['block_id' => $blocks[2]->id, 'text' => 'Stale slot edit.'],
            ],
        ])->assertStatus(409)
            ->assertJsonPath('current_slot_version', $updatedDraft['slot_version']);
    }

    public function test_hv_draft_finalisation_records_commit_audit_and_next_stage(): void
    {
        $assignment = $this->assignment();
        $block = $assignment->blocks()->firstOrFail();
        Sanctum::actingAs($assignment->translator);

        $draft = $this->patchJson("/api/translator/slot/{$assignment->slot_id}/draft", [
            'slot_version' => $block->version,
            'edits' => [
                ['block_id' => $block->id, 'text' => 'Hindi version ready.'],
            ],
        ])->assertOk()->json();

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/finalise", [
            'slot_version' => $draft['slot_version'],
            'draft_type' => 'hv',
            'note' => 'HV final check complete.',
        ])->assertOk()
            ->assertJsonPath('assignment.status', 'hv_draft_finalised')
            ->assertJsonPath('assignment.ai_translation_meta.draft_finalisation.draft_type', 'hv')
            ->assertJsonPath('next_stage', 'supervisor');

        $this->assertDatabaseHas('translator_commits', ['assignment_id' => $assignment->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.draft.finalised']);
    }

    public function test_ev_draft_finalisation_is_supported_for_hi_to_en_assignments(): void
    {
        $assignment = $this->assignment();
        $translator = $this->translator('TRN-HI-001');
        $assignment->forceFill([
            'translator_user_id' => $translator->id,
            'language_pair' => 'hi_to_en',
        ])->save();
        Sanctum::actingAs($translator);

        $draft = $this->getJson("/api/translator/slot/{$assignment->slot_id}/draft")
            ->assertOk()
            ->json();

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/finalise", [
            'slot_version' => $draft['slot_version'],
            'draft_type' => 'ev',
        ])->assertOk()
            ->assertJsonPath('assignment.status', 'ev_draft_finalised')
            ->assertJsonPath('assignment.ai_translation_meta.draft_finalisation.draft_type', 'ev');
    }

    public function test_finalisation_returns_conflict_resolution_payload_for_stale_slot_version(): void
    {
        $assignment = $this->assignment();
        $block = $assignment->blocks()->firstOrFail();
        Sanctum::actingAs($assignment->translator);

        $this->patchJson("/api/translator/slot/{$assignment->slot_id}/draft", [
            'slot_version' => $block->version,
            'edits' => [
                ['block_id' => $block->id, 'text' => 'Fresh text before finalise.'],
            ],
        ])->assertOk();

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/finalise", [
            'slot_version' => $block->version,
            'draft_type' => 'hv',
        ])->assertStatus(409)
            ->assertJsonPath('resolution', 'reload_latest_draft')
            ->assertJsonPath('current_slot_version', $block->fresh()->version);
    }

    public function test_finalisation_detects_stale_slot_version_after_non_max_block_edit(): void
    {
        $assignment = $this->assignment();
        $blocks = $assignment->blocks()->get()->values();
        $blocks[0]->forceFill(['version' => 4])->save();
        $blocks[1]->forceFill(['version' => 1])->save();
        Sanctum::actingAs($assignment->translator);

        $draft = $this->getJson("/api/translator/slot/{$assignment->slot_id}/draft")
            ->assertOk()
            ->assertJsonPath('slot_version', 4)
            ->json();

        $this->putJson("/api/translator/assignments/{$assignment->id}/blocks/{$blocks[1]->id}", [
            'text' => 'Independent edit before finalise.',
            'version' => $blocks[1]->version,
        ])->assertOk();

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/finalise", [
            'slot_version' => $draft['slot_version'],
            'draft_type' => 'hv',
        ])->assertStatus(409)
            ->assertJsonPath('resolution', 'reload_latest_draft')
            ->assertJsonPath('current_slot_version', 5);
    }

    public function test_finalised_draft_cannot_be_patched_again(): void
    {
        $assignment = $this->assignment();
        $block = $assignment->blocks()->firstOrFail();
        Sanctum::actingAs($assignment->translator);

        $draft = $this->getJson("/api/translator/slot/{$assignment->slot_id}/draft")
            ->assertOk()
            ->json();

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/finalise", [
            'slot_version' => $draft['slot_version'],
            'draft_type' => 'hv',
        ])->assertOk();

        $this->patchJson("/api/translator/slot/{$assignment->slot_id}/draft", [
            'slot_version' => $block->fresh()->version,
            'edits' => [
                ['block_id' => $block->id, 'text' => 'Late edit.'],
            ],
        ])->assertStatus(423);
    }

    public function test_regional_language_flag_requires_code_and_is_stored_in_meta(): void
    {
        $assignment = $this->assignment();
        Sanctum::actingAs($assignment->translator);

        $draft = $this->getJson("/api/translator/slot/{$assignment->slot_id}/draft")
            ->assertOk()
            ->json();

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/finalise", [
            'slot_version' => $draft['slot_version'],
            'draft_type' => 'hv',
            'regional_language_flag' => true,
        ])->assertUnprocessable();

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/finalise", [
            'slot_version' => $draft['slot_version'],
            'draft_type' => 'hv',
            'regional_language_flag' => true,
            'regional_language_code' => 'ta',
        ])->assertOk()
            ->assertJsonPath('assignment.ai_translation_meta.draft_finalisation.regional_language_flag', true)
            ->assertJsonPath('assignment.ai_translation_meta.draft_finalisation.regional_language_code', 'ta');
    }

    public function test_forward_chain_translator_to_supervisor_to_director_with_audit(): void
    {
        $assignment = $this->assignment();
        Sanctum::actingAs($assignment->translator);

        $draft = $this->getJson("/api/translator/slot/{$assignment->slot_id}/draft")
            ->assertOk()
            ->json();

        $this->postJson("/api/translator/slot/{$assignment->slot_id}/finalise", [
            'slot_version' => $draft['slot_version'],
            'draft_type' => 'hv',
        ])->assertOk();

        $this->postJson("/api/translator/assignments/{$assignment->id}/forward-supervisor", [
            'note' => 'Ready for supervisor.',
        ])->assertOk()
            ->assertJsonPath('assignment.status', 'supervisor_review')
            ->assertJsonPath('assignment.ai_translation_meta.current_stage', 'supervisor');

        Sanctum::actingAs($this->supervisor('SUP-EN-001'));
        $this->postJson("/api/translator/reviewer/assignments/{$assignment->id}/forward-director", [
            'note' => 'Reviewer cleared.',
        ])->assertOk()
            ->assertJsonPath('assignment.status', 'director_review')
            ->assertJsonPath('assignment.ai_translation_meta.current_stage', 'director');

        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.forward.supervisor']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.forward.director']);
    }

    public function test_channel_auth_allows_assigned_translator_and_denies_unassigned_translator(): void
    {
        $assignment = $this->assignment();

        $this->actingAs($assignment->translator);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-translator.slot.{$assignment->slot_id}",
        ])->assertOk();

        $this->actingAs($this->translator('TRN-HI-001'));
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-translator.slot.{$assignment->slot_id}",
        ])->assertForbidden();
    }

    public function test_glossary_crud_by_translator_role(): void
    {
        Sanctum::actingAs($this->translator('TRN-EN-001'));

        $id = $this->postJson('/api/translator/glossary', [
            'term_source' => 'Test Parliamentary Term',
            'term_target' => 'परीक्षण संसदीय शब्द',
            'language_pair' => 'en_to_hi',
            'domain' => 'parliamentary',
            'notes' => 'Test term',
        ])->assertCreated()->json('id');

        $this->putJson("/api/translator/glossary/{$id}", [
            'term_source' => 'Test Parliamentary Term',
            'term_target' => 'परीक्षण शब्द',
            'language_pair' => 'en_to_hi',
            'domain' => 'parliamentary',
            'notes' => 'Updated',
        ])->assertOk()
            ->assertJsonPath('term_target', 'परीक्षण शब्द');

        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.glossary.create']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.glossary.update']);
    }

    private function assignment(): TranslatorAssignment
    {
        return TranslatorAssignment::query()->where('language_pair', 'en_to_hi')->firstOrFail();
    }

    private function translator(string $employeeId): User
    {
        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }

    private function supervisor(string $employeeId): User
    {
        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
