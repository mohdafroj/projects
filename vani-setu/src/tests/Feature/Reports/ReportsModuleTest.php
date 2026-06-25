<?php

namespace Tests\Feature\Reports;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use App\Modules\Reports\Models\ReportSnapshot;
use Laravel\Sanctum\Sanctum;
use Tests\ModuleTestCase;

class ReportsModuleTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedModuleBase();
    }

    public function test_summary_supports_multidimensional_filters_and_audits(): void
    {
        User::query()->role('reporter')->update(['section' => 'Reporting']);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/reports/summary?workflow_stage=reporter&section=Reporting&content_type=original&date_from=2025-12-11&date_to=2025-12-11')
            ->assertOk()
            ->assertJsonPath('filters.workflow_stage.0', 'reporter')
            ->assertJsonPath('filters.section.0', 'Reporting')
            ->assertJsonPath('filters.content_type', 'original')
            ->assertJsonPath('totals.sittings', 1)
            ->assertJsonStructure([
                'coverage' => ['users', 'sections', 'workflow'],
            ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.summary.viewed']);
    }

    public function test_chart_data_and_snapshot_capture_are_audited(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/reports/charts?workflow_stage=reporter')
            ->assertOk()
            ->assertJsonStructure(['by_date', 'by_language', 'by_content_type', 'by_workflow', 'by_section']);

        $snapshotId = $this->postJson('/api/reports/snapshots', [
            'name' => 'Daily workflow snapshot',
            'workflow_stage' => ['reporter'],
            'content_type' => 'all',
        ])->assertCreated()
            ->assertJsonPath('name', 'Daily workflow snapshot')
            ->json('id');

        $snapshot = ReportSnapshot::query()->findOrFail($snapshotId);
        $this->assertNotNull($snapshot->captured_audit_log_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.charts.viewed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.snapshot.captured']);
    }

    public function test_csv_and_pdf_exports_write_reports_audit_actions(): void
    {
        Sanctum::actingAs($this->admin());

        $this->get('/api/reports/export?format=csv&workflow_stage=reporter')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('sitting_date,slot_code,sequence,workflow_stage');

        $this->get('/api/reports/export?format=pdf&workflow_stage=reporter')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('%PDF-1.4');

        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.export.csv']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.export.pdf']);
    }

    public function test_reports_are_restricted_to_senior_and_admin_roles(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/reports/summary')->assertForbidden();
    }

    private function admin(): User
    {
        return User::query()->role('admin')->firstOrFail();
    }
}
