<?php

namespace Tests\Feature;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use App\Modules\Formatting\Models\FormattingJob;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Js\Models\SuggestedEdit;
use App\Modules\Synopsis\Models\SynopsisDocument;
use App\Modules\Translator\Models\TranslatorAssignment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndToEndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const TRACE = [
        'capture.slot.commit',
        'capture.workflow.forward',
        'chief.block.edit',
        'chief.consolidation.commit:en',
        'chief.consolidation.commit:hi',
        'translator.ai.requested',
        'translator.assignment.commit',
        'regional.block.routed',
        'js.se.accept',
        'js.window.forward_sg',
        'sg.window.open',
        'sg.window.sign',
        'js.window.approve',
        'formatting.crc.compiled',
        'director.job.queued',
        'director.crc.generated',
        'director.sansad.pushed',
        'synopsis.draft.generated',
        'synopsis.draft.submit',
        'synopsis.finalise',
        'synopsis.pdf.export',
        'reports.snapshot.captured',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        putenv('DIRECTOR_CRC_DISK=director_crc');
        $_ENV['DIRECTOR_CRC_DISK'] = 'director_crc';
        Storage::fake('local');
        Storage::fake('director_crc');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_one_slot_reaches_director_publish_with_ordered_audit_trace(): void
    {
        Http::fake([
            '*/v1/translate' => Http::response([
                'translation' => 'Treasury Benches translated by test gateway.',
                'confidence' => 0.91,
                'model_version' => 'indictrans2-e2e',
            ]),
        ]);

        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        $assignment = SlotAssignment::query()
            ->where('slot_id', $slot->id)
            ->where('lang_role', 'en')
            ->firstOrFail();
        $block = Block::query()
            ->where('slot_id', $slot->id)
            ->where('chief_lang', 'en')
            ->orderBy('sequence')
            ->firstOrFail();
        $windowA = ChiefConsolidation::query()->where('window_code', 'A')->firstOrFail();
        $windowB = ChiefConsolidation::query()->where('window_code', 'B')->firstOrFail();
        $jsWindow = JsWindow::query()->where('window_code', '1200-1300')->firstOrFail();

        $this->makeChiefInputsReady($windowA, $slot);
        $windowB->forceFill(['status' => 'dual_committed'])->save();

        Sanctum::actingAs($assignment->user);
        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'en'])
            ->assertOk()
            ->assertJsonPath('workflow_stage', 'supervisor');

        Sanctum::actingAs($this->user('SUP-EN-001'));
        $this->postJson("/api/slot-assignments/{$assignment->id}/forward", [
            'note' => 'Ready for chief consolidation.',
        ])->assertOk()
            ->assertJsonPath('assignment.workflow_stage', 'chief');

        Sanctum::actingAs($this->user('CHF-EN-001'));
        $this->putJson("/api/chief/consolidations/{$windowA->id}/blocks/{$block->id}", [
            'text' => $block->text.' Chief final correction.',
            'version' => $block->version,
        ])->assertOk();
        $this->postJson("/api/chief/consolidations/{$windowA->id}/commit", ['lang_side' => 'en'])
            ->assertOk()
            ->assertJsonPath('consolidation.status', 'en_committed');

        Sanctum::actingAs($this->user('CHF-HI-001'));
        $this->postJson("/api/chief/consolidations/{$windowA->id}/commit", ['lang_side' => 'hi'])
            ->assertOk()
            ->assertJsonPath('consolidation.status', 'dual_committed');

        $translatorAssignment = TranslatorAssignment::query()
            ->where('slot_id', $slot->id)
            ->where('language_pair', 'en_to_hi')
            ->firstOrFail();
        Sanctum::actingAs($this->user('TRN-EN-001'));
        $this->postJson("/api/translator/assignments/{$translatorAssignment->id}/request-ai")
            ->assertOk()
            ->assertJsonPath('assignment.status', 'in_review');
        $this->postJson("/api/translator/assignments/{$translatorAssignment->id}/commit")
            ->assertOk()
            ->assertJsonPath('assignment.status', 'forwarded');

        Sanctum::actingAs($this->user('TRN-TA-001'));
        $regionalCaseId = $this->postJson('/api/regional/cases', [
            'block_id' => $block->id,
            'source_text' => 'தமிழில் உரை உள்ளது',
            'target_language' => 'hi',
        ])->assertCreated()
            ->assertJsonPath('case.source_language', 'ta')
            ->json('case.id');
        $this->postJson("/api/regional/cases/{$regionalCaseId}/translate", [
            'specialist_translation' => 'क्षेत्रीय विशेषज्ञ अनुवाद',
        ])->assertOk()
            ->assertJsonPath('case.status', 'translated');
        $this->postJson("/api/regional/cases/{$regionalCaseId}/cross-check", [
            'result' => 'passed',
            'score' => 97,
            'notes' => 'E2E terminology checked.',
        ])->assertOk()
            ->assertJsonPath('case.status', 'cross_checked');
        $this->postJson("/api/regional/cases/{$regionalCaseId}/commit")
            ->assertOk()
            ->assertJsonPath('case.status', 'committed');

        $suggestedEdit = $this->suggestedEdit($jsWindow, $block);
        Sanctum::actingAs($this->user('JS-001'));
        $this->postJson("/api/js/windows/{$jsWindow->id}/suggested-edits/{$suggestedEdit->id}/accept")
            ->assertOk()
            ->assertJsonPath('suggested_edit.state', 'accepted');
        $this->postJson("/api/js/windows/{$jsWindow->id}/forward-sg", ['note' => 'Ready for SG decision.'])
            ->assertOk()
            ->assertJsonPath('window.status', 'sent_to_sg');

        Sanctum::actingAs($this->user('SG-001'));
        $this->postJson("/api/sg/windows/{$jsWindow->id}/open")
            ->assertOk()
            ->assertJsonPath('review.window_id', $jsWindow->id);
        $this->postJson("/api/sg/windows/{$jsWindow->id}/sign")
            ->assertOk()
            ->assertJsonPath('window.status', 'sg_returned');

        Sanctum::actingAs($this->user('JS-001'));
        $this->postJson("/api/js/windows/{$jsWindow->id}/approve-publish")
            ->assertOk()
            ->assertJsonPath('window.status', 'approved');

        Sanctum::actingAs($this->user('FMT-001'));
        $formattingJobId = $this->postJson('/api/formatting/jobs', [
            'window_id' => $jsWindow->id,
            'artifact_type' => 'fv',
        ])->assertCreated()
            ->assertJsonPath('job.status', 'draft')
            ->json('job.id');
        $this->postJson("/api/formatting/jobs/{$formattingJobId}/validate")
            ->assertOk()
            ->assertJsonPath('job.status', 'validated');
        $this->postJson("/api/formatting/jobs/{$formattingJobId}/crc")
            ->assertOk()
            ->assertJsonPath('job.status', 'crc_ready');
        $this->assertNotNull(FormattingJob::query()->findOrFail($formattingJobId)->crc_path);

        Sanctum::actingAs($this->user('DIR-001'));
        $jobId = $this->getJson('/api/director/inbox')
            ->assertOk()
            ->assertJsonFragment(['window_id' => $jsWindow->id, 'status' => 'queued'])
            ->json('0.id');
        $this->postJson("/api/director/jobs/{$jobId}/publish")
            ->assertOk()
            ->assertJsonPath('job.status', 'published');

        $this->user('CHF-EN-001')->assignRole('synopsis_writer');
        Sanctum::actingAs($this->user('CHF-EN-001'));
        $this->postJson("/api/synopsis/chunks/{$windowA->id}/generate")
            ->assertOk()
            ->assertJsonPath('document.status', 'draft');
        $this->postJson("/api/synopsis/chunks/{$windowA->id}/submit")
            ->assertOk()
            ->assertJsonPath('document.status', 'submitted');
        $this->postJson("/api/synopsis/chunks/{$windowA->id}/finalise")
            ->assertOk()
            ->assertJsonPath('document.status', 'final');
        $export = $this->get("/api/synopsis/chunks/{$windowA->id}/export.pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertSame(hash('sha256', $export->getContent()), $export->headers->get('X-Vani-Setu-Pdf-Sha256'));
        $this->assertSame('final', SynopsisDocument::query()->where('consolidation_id', $windowA->id)->firstOrFail()->status);

        Sanctum::actingAs($this->user('ADM-001'));
        $this->postJson('/api/reports/snapshots', [
            'name' => 'Track A E2E snapshot',
            'workflow_stage' => ['chief'],
            'content_type' => 'all',
        ])->assertCreated()
            ->assertJsonPath('name', 'Track A E2E snapshot');

        $this->assertSame(self::TRACE, $this->workflowAuditTrace());
        $this->assertSame(count(self::TRACE), $this->workflowAuditLogs()->count());

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    private function makeChiefInputsReady(ChiefConsolidation $consolidation, Slot $drivenSlot): void
    {
        SlotAssignment::query()
            ->whereIn('slot_id', $consolidation->slotIdsInWindow())
            ->where('slot_id', '!=', $drivenSlot->id)
            ->update([
                'status' => 'committed',
                'workflow_stage' => 'chief',
                'last_workflow_action_at' => now(),
            ]);
    }

    private function suggestedEdit(JsWindow $window, Block $block): SuggestedEdit
    {
        return SuggestedEdit::query()->create([
            'window_id' => $window->id,
            'source' => 'member',
            'source_name' => 'End-to-end member correction',
            'block_id' => $block->id,
            'before' => $block->fresh()->text,
            'after' => $block->fresh()->text.' JS accepted correction.',
            'reason' => 'End-to-end workflow accepted suggested edit.',
            'state' => 'pending',
        ]);
    }

    /**
     * @return list<string>
     */
    private function workflowAuditTrace(): array
    {
        return $this->workflowAuditLogs()
            ->map(function (AuditLog $log): string {
                if ($log->action === 'chief.consolidation.commit') {
                    return $log->action.':'.$log->payload['lang_side'];
                }

                return $log->action;
            })
            ->values()
            ->all();
    }

    private function workflowAuditLogs()
    {
        return AuditLog::query()
            ->whereIn('action', array_unique(array_map(
                fn (string $action): string => explode(':', $action, 2)[0],
                self::TRACE,
            )))
            ->orderBy('id')
            ->get();
    }

    private function user(string $employeeId): User
    {
        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
