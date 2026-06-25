<?php

namespace Tests\Feature\Formatting;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use App\Modules\Formatting\Models\FormattingJob;
use App\Modules\Js\Models\JsWindow;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormattingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_happy_path_formats_validates_crc_and_dispatches_floor_version(): void
    {
        $window = $this->approvedWindow();
        Sanctum::actingAs($this->formatter());

        $jobId = $this->postJson('/api/formatting/jobs', [
            'window_id' => $window->id,
            'artifact_type' => 'fv',
        ])
            ->assertCreated()
            ->assertJsonPath('job.status', 'draft')
            ->assertJsonPath('job.metadata.source', 'DVOT-Yogesh')
            ->assertJsonFragment(['kind' => 'bifurcation'])
            ->assertJsonFragment(['kind' => 'plot'])
            ->assertJsonFragment(['kind' => 'oih'])
            ->json('job.id');

        $this->postJson("/api/formatting/jobs/{$jobId}/validate")
            ->assertOk()
            ->assertJsonPath('job.status', 'validated')
            ->assertJsonPath('job.policy_report.ok', true);

        $crc = $this->postJson("/api/formatting/jobs/{$jobId}/crc")
            ->assertOk()
            ->assertJsonPath('job.status', 'crc_ready')
            ->json('job.crc_path');

        Storage::disk('local')->assertExists($crc);

        $this->postJson("/api/formatting/jobs/{$jobId}/dispatch")
            ->assertOk()
            ->assertJsonPath('job.status', 'dispatched');
    }

    public function test_policy_rejects_missing_dvot_yogesh_metadata(): void
    {
        $window = $this->approvedWindow();
        Sanctum::actingAs($this->formatter());
        $jobId = $this->postJson('/api/formatting/jobs', [
            'window_id' => $window->id,
            'artifact_type' => 'ev',
        ])->json('job.id');

        FormattingJob::query()->findOrFail($jobId)->forceFill([
            'metadata' => ['source' => 'manual'],
        ])->save();

        $this->postJson("/api/formatting/jobs/{$jobId}/validate")
            ->assertStatus(422)
            ->assertJsonPath('policy_report.ok', false)
            ->assertJsonFragment(['DVOT-Yogesh metadata is required.']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'formatting.policy.checked']);
    }

    public function test_audit_trace_records_formatting_actions_and_chain_verifies(): void
    {
        $window = $this->approvedWindow();
        Sanctum::actingAs($this->formatter());
        $jobId = $this->postJson('/api/formatting/jobs', [
            'window_id' => $window->id,
            'artifact_type' => 'hv',
        ])->json('job.id');

        $this->postJson("/api/formatting/jobs/{$jobId}/validate")->assertOk();
        $this->postJson("/api/formatting/jobs/{$jobId}/crc")->assertOk();

        $this->getJson("/api/formatting/jobs/{$jobId}/audit")
            ->assertOk()
            ->assertJsonFragment(['action' => 'formatting.job.created'])
            ->assertJsonFragment(['action' => 'formatting.policy.checked'])
            ->assertJsonFragment(['action' => 'formatting.crc.compiled']);

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    public function test_transition_policy_requires_approved_window_and_ordered_statuses(): void
    {
        $window = $this->approvedWindow('open');
        Sanctum::actingAs($this->formatter());

        $this->postJson('/api/formatting/jobs', [
            'window_id' => $window->id,
            'artifact_type' => 'fv',
        ])->assertStatus(422);

        $window->forceFill(['status' => 'approved'])->save();
        $jobId = $this->postJson('/api/formatting/jobs', [
            'window_id' => $window->id,
            'artifact_type' => 'fv',
        ])->assertCreated()->json('job.id');

        $this->postJson("/api/formatting/jobs/{$jobId}/crc")->assertStatus(422);
        $this->postJson("/api/formatting/jobs/{$jobId}/validate")->assertOk();
        $this->postJson("/api/formatting/jobs/{$jobId}/crc")->assertOk();

        $this->assertDatabaseHas('formatting_transitions', [
            'job_id' => $jobId,
            'action' => 'create',
            'to_status' => 'draft',
        ]);
        $this->assertDatabaseHas('formatting_transitions', [
            'job_id' => $jobId,
            'action' => 'validate',
            'to_status' => 'validated',
        ]);
        $this->assertDatabaseHas('formatting_transitions', [
            'job_id' => $jobId,
            'action' => 'crc',
            'to_status' => 'crc_ready',
        ]);
    }

    private function formatter(): User
    {
        return User::query()->where('employee_id', 'FMT-001')->firstOrFail();
    }

    private function approvedWindow(string $status = 'approved'): JsWindow
    {
        $sitting = Sitting::query()->create([
            'session_no' => 2026,
            'sitting_no' => random_int(100, 999),
            'sitting_date' => '2026-05-20',
            'status' => 'closed',
        ]);
        $slot = Slot::query()->create([
            'sitting_id' => $sitting->id,
            'code' => 'F1',
            'start_offset_ms' => 0,
            'duration_ms' => 300000,
            'topic' => 'Official Debates',
            'status' => 'committed_full',
        ]);
        $member = Member::query()->where('category', 'member')->firstOrFail();

        Block::query()->create([
            'slot_id' => $slot->id,
            'sequence' => 1,
            'start_ms' => 0,
            'end_ms' => 1000,
            'original_lang' => 'en',
            'chief_lang' => 'en',
            'ai_action' => 'native',
            'ai_text' => 'The paper laid on the table is referenced.',
            'text' => 'The paper laid on the table is referenced.',
            'member_id' => $member->id,
            'version' => 1,
            'reporter_edit_count' => 0,
        ]);
        Block::query()->create([
            'slot_id' => $slot->id,
            'sequence' => 2,
            'start_ms' => 1000,
            'end_ms' => 2000,
            'original_lang' => 'hi',
            'chief_lang' => 'hi',
            'ai_action' => 'native',
            'ai_text' => 'मूल वक्तव्य हिन्दी में है।',
            'text' => 'मूल वक्तव्य हिन्दी में है।',
            'member_id' => $member->id,
            'version' => 1,
            'reporter_edit_count' => 0,
        ]);

        return JsWindow::query()->create([
            'sitting_id' => $sitting->id,
            'window_code' => '0000-0100',
            'starts_at_offset_ms' => 0,
            'duration_ms' => 3600000,
            'status' => $status,
        ]);
    }
}
