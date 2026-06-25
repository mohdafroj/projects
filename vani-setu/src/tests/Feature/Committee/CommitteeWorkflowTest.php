<?php

namespace Tests\Feature\Committee;

use App\Modules\CommitteeSittings\Models\CommitteeDocument;
use App\Modules\CommitteeSittings\Models\CommitteeSitting;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommitteeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['taskUid' => 1])]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_committee_meeting_walks_to_laid_report_with_audit_trace(): void
    {
        [$committeeSitting, $block] = $this->createCommitteeSittingAndCaptureBlock(inCamera: true);

        Sanctum::actingAs($this->user('COM-SEC-001'));
        $this->postJson("/api/committee/sittings/{$committeeSitting->id}/forward", [
            'from_stage' => 'committee_capture',
            'to_stage' => 'committee_chief',
            'note' => 'Committee evidence ready for consolidation.',
        ])->assertOk();

        $documentId = $this->postJson("/api/committee/sittings/{$committeeSitting->id}/chief-commit")
            ->assertOk()
            ->assertJsonPath('document.status', 'chief_consolidated')
            ->json('document.id');

        $this->postJson("/api/committee/documents/{$documentId}/secretariat-review")
            ->assertOk()
            ->assertJsonPath('document.status', 'secretariat_reviewed');

        $this->postJson("/api/committee/documents/{$documentId}/draft-report", [
            'title' => 'Draft DRPSC Report',
            'body' => 'Draft report with minutes, evidence, final recommendation, and dissent note slot.',
        ])->assertOk()
            ->assertJsonPath('document.status', 'drafted');

        Sanctum::actingAs($this->user('COM-CHR-001'));
        $this->postJson("/api/committee/documents/{$documentId}/chair-sign")
            ->assertOk()
            ->assertJsonPath('document.status', 'chair_signed');

        Sanctum::actingAs($this->user('COM-SEC-001'));
        $this->postJson("/api/committee/documents/{$documentId}/lay")
            ->assertOk()
            ->assertJsonPath('document.status', 'laid_before_house')
            ->assertJsonPath('document.prism_archive_ref', fn ($ref) => str_starts_with($ref, 'PRISM-COM-'));

        $trace = AuditLog::query()
            ->whereIn('action', [
                'committee.sitting.create',
                'committee.capture.slot.commit',
                'committee.workflow.forward',
                'committee.chief.consolidation.commit',
                'committee.secretariat.review',
            'committee.report.draft',
            'committee.chair.sign',
            'committee.report.laid',
            'in_camera.flag.applied',
            'reports.committee.snapshot.captured',
            ])
            ->orderBy('id')
            ->pluck('action')
            ->all();

        $this->assertSame([
            'committee.sitting.create',
            'committee.capture.slot.commit',
            'committee.workflow.forward',
            'committee.chief.consolidation.commit',
            'committee.secretariat.review',
            'committee.report.draft',
            'committee.chair.sign',
            'committee.report.laid',
            'in_camera.flag.applied',
            'reports.committee.snapshot.captured',
        ], $trace);

        $this->assertSame('laid_before_house', CommitteeDocument::query()->findOrFail($documentId)->status);
        $this->artisan('audit:verify')->expectsOutputToContain('Chain intact')->assertExitCode(0);
    }

    public function test_in_camera_block_access_is_restricted_to_authorised_committee_roles(): void
    {
        [$committeeSitting, $block] = $this->createCommitteeSittingAndCaptureBlock(inCamera: true);

        Sanctum::actingAs($this->user('COM-OBS-001'));
        $this->getJson("/api/in-camera/blocks/{$block->id}")
            ->assertForbidden();

        Sanctum::actingAs($this->user('COM-SEC-001'));
        $this->getJson("/api/in-camera/blocks/{$block->id}")
            ->assertOk()
            ->assertJsonPath('text', 'Witness evidence committed for committee record.');

        $this->assertSame($committeeSitting->committee_id, $block->fresh()->getAttribute('committee_id'));
    }

    /**
     * @return array{CommitteeSitting, Block}
     */
    private function createCommitteeSittingAndCaptureBlock(bool $inCamera = false): array
    {
        Sanctum::actingAs($this->user('COM-SEC-001'));

        $committeeSittingId = $this->postJson('/api/committee/sittings', [
            'committee_code' => 'DRPSC-IT',
            'committee_name' => 'Department-related Parliamentary Standing Committee on Information Technology',
            'committee_type' => 'DRPSC',
            'meeting_no' => '1',
            'scheduled_at' => '2026-06-01T10:00:00+05:30',
            'venue' => 'Committee Room A',
            'terms_of_reference' => 'Examine digital governance evidence.',
            'in_camera_default' => $inCamera,
            'witnesses' => [['name' => 'Witness A', 'organisation' => 'Ministry']],
            'observers' => [['name' => 'Observer A']],
        ])->assertCreated()
            ->assertJsonPath('sitting.committee.type', 'DRPSC')
            ->json('sitting.id');

        $committeeSitting = CommitteeSitting::query()->findOrFail($committeeSittingId);
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        $block = Block::query()->where('slot_id', $slot->id)->orderBy('sequence')->firstOrFail();

        $this->postJson("/api/committee/sittings/{$committeeSitting->id}/capture-slots", [
            'slot_id' => $slot->id,
            'block_id' => $block->id,
            'text' => 'Witness evidence committed for committee record.',
            'in_camera' => $inCamera,
        ])->assertOk()
            ->assertJsonPath('block.source_type', 'committee')
            ->assertJsonPath('block.in_camera_flag', $inCamera);

        return [$committeeSitting->fresh(), $block->fresh()];
    }

    private function user(string $employeeId): User
    {
        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
