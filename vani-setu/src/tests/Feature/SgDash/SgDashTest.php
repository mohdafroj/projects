<?php

namespace Tests\Feature\SgDash;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Js\Models\ExpungeCandidate;
use App\Modules\Js\Models\JsSgHandoff;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Sg\Models\SgReview;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SgDashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_pipeline_ageing_and_drilldown_are_scoped_to_sitting_date(): void
    {
        $sg = User::query()->where('employee_id', 'SG-001')->firstOrFail();
        $sent = $this->window('2026-05-19', '0900-1000', 'sent_to_sg');
        $returned = $this->window('2026-05-19', '1000-1100', 'sg_returned');
        $otherDate = $this->window('2026-05-20', '0900-1000', 'sent_to_sg');
        $this->handoff($sent, now()->subMinutes(75));
        $this->handoff($returned, now()->subMinutes(120), now()->subMinutes(15));
        $this->handoff($otherDate, now()->subMinutes(10));
        Sanctum::actingAs($sg);

        $this->getJson('/api/sg-dash/pipeline?date=2026-05-19')
            ->assertOk()
            ->assertJsonPath('summary.windows_total', 2)
            ->assertJsonPath('summary.sent_to_sg', 1)
            ->assertJsonPath('summary.returned_from_sg', 1)
            ->assertJsonPath('summary.pending_expunges', 1);

        $this->getJson('/api/sg-dash/ageing?date=2026-05-19')
            ->assertOk()
            ->assertJsonPath('windows.0.window.id', $sent->id)
            ->assertJsonPath('windows.0.bucket', '61_120');

        $this->getJson('/api/sg-dash/windows?date=2026-05-19&status=sent_to_sg')
            ->assertOk()
            ->assertJsonCount(1, 'windows')
            ->assertJsonPath('windows.0.id', $sent->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'sg_dash.pipeline']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sg_dash.ageing']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sg_dash.drilldown']);
    }

    public function test_dates_feed_and_window_detail_include_sg_review_handoff_and_audit_context(): void
    {
        $sg = User::query()->where('employee_id', 'SG-001')->firstOrFail();
        $window = $this->window('2026-05-19', '0900-1000', 'sg_returned');
        $handoff = $this->handoff($window, now()->subHours(2), now()->subHour());
        SgReview::query()->create([
            'window_id' => $window->id,
            'sg_user_id' => $sg->id,
            'opened_at' => now()->subMinutes(90),
            'signed_at' => now()->subHour(),
            'dsc_serial' => 'DSC-SG-TEST-001',
            'confirmed_expunges' => 1,
            'manual_expunges' => 0,
            'audit_log_id_open' => $handoff->sent_audit_log_id,
            'audit_log_id_sign' => $handoff->returned_audit_log_id,
        ]);
        Sanctum::actingAs($sg);

        $this->getJson('/api/sg-dash/dates')
            ->assertOk()
            ->assertJsonFragment(['date' => '2026-05-19']);

        $this->getJson('/api/sg-dash/feed?date=2026-05-19')
            ->assertOk()
            ->assertJsonFragment(['title' => 'SG signed decision'])
            ->assertJsonFragment(['title' => 'SG returned signed window']);

        $this->getJson("/api/sg-dash/windows/{$window->id}")
            ->assertOk()
            ->assertJsonPath('window.id', $window->id)
            ->assertJsonPath('window.review.dsc_serial', 'DSC-SG-TEST-001')
            ->assertJsonCount(1, 'window.handoffs');

        $this->assertDatabaseHas('audit_logs', ['action' => 'sg_dash.date_switcher']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sg_dash.feed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sg_dash.window']);
    }

    private function window(string $date, string $code, string $status): JsWindow
    {
        $sitting = Sitting::query()->firstOrCreate(
            ['session_no' => 2026, 'sitting_no' => (int) str_replace('-', '', $date)],
            ['sitting_date' => $date, 'status' => 'live'],
        );
        $slot = Slot::query()->create([
            'sitting_id' => $sitting->id,
            'code' => $code,
            'start_offset_ms' => 0,
            'duration_ms' => 300000,
            'topic' => 'SG dashboard test',
            'status' => 'committed_full',
        ]);
        $block = Block::query()->create([
            'slot_id' => $slot->id,
            'sequence' => 1,
            'start_ms' => 0,
            'end_ms' => 1000,
            'original_lang' => 'en',
            'chief_lang' => 'en',
            'ai_action' => 'native',
            'ai_text' => 'dashboard expunge phrase',
            'text' => 'dashboard expunge phrase',
            'version' => 1,
            'reporter_edit_count' => 0,
        ]);
        $window = JsWindow::query()->create([
            'sitting_id' => $sitting->id,
            'window_code' => $code,
            'starts_at_offset_ms' => 0,
            'duration_ms' => 3600000,
            'status' => $status,
        ]);
        ExpungeCandidate::query()->create([
            'window_id' => $window->id,
            'block_id' => $block->id,
            'word' => 'phrase',
            'grounds' => 'Test grounds',
            'master_db_ref' => 'MDB-SG-DASH',
            'state' => $status === 'sg_returned' ? 'confirmed' : 'pending',
        ]);

        return $window;
    }

    private function handoff(JsWindow $window, mixed $sentAt, mixed $returnedAt = null): JsSgHandoff
    {
        $audit = app(AuditLogger::class);
        $sentLog = $audit->log('js.window.forward_sg', $window, ['window_id' => $window->id]);
        $returnedLog = $returnedAt ? $audit->log('sg.window.sign', $window, ['window_id' => $window->id]) : null;

        return JsSgHandoff::query()->create([
            'window_id' => $window->id,
            'sent_at' => $sentAt,
            'sent_audit_log_id' => $sentLog->id,
            'returned_at' => $returnedAt,
            'returned_audit_log_id' => $returnedLog?->id,
            'dsc_serial' => $returnedAt ? 'DSC-SG-TEST-001' : null,
            'sg_user_id' => $returnedAt ? User::query()->where('employee_id', 'SG-001')->value('id') : null,
            'confirmed_expunges' => $window->expungeCandidatesCount('confirmed'),
            'manual_expunges' => 0,
        ]);
    }
}
