<?php

namespace Tests\Feature;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use App\Modules\Js\Models\ExpungeCandidate;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Sg\Models\SgManualExpunge;
use App\Modules\Sg\Models\SgReview;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsAuditChains;
use Tests\TestCase;

class M16SgWorkspaceTest extends TestCase
{
    use AssertsAuditChains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_sg_workspace_routes_render_shell(): void
    {
        $window = $this->windowWithCandidates('sent_to_sg');

        $this->get('/app/sg')
            ->assertOk()
            ->assertSee('data-workspace="sg"', false)
            ->assertSee('M16 SG Workspace');

        $this->get("/app/sg/windows/{$window->id}")
            ->assertOk()
            ->assertSee('data-initial-window="'.$window->id.'"', false);
    }

    public function test_sg_login_and_tray_loads(): void
    {
        $window = $this->windowWithCandidates('sent_to_sg');

        $login = $this->postJson('/api/auth/login', [
            'employee_id' => 'SG-001',
            'password' => 'sg123',
        ])->assertOk()
            ->assertJsonPath('roles.0', 'sg');

        $this->withToken($login->json('token'))
            ->getJson('/api/sg/tray')
            ->assertOk()
            ->assertJsonFragment(['id' => $window->id, 'status' => 'sent_to_sg']);
    }

    public function test_non_sg_cannot_access_sg_tray(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'JS-001')->firstOrFail());

        $this->getJson('/api/sg/tray')->assertForbidden();
    }

    public function test_sg_detail_open_decide_manual_and_history(): void
    {
        $window = $this->windowWithCandidates('sent_to_sg');
        $candidate = ExpungeCandidate::query()->where('window_id', $window->id)->firstOrFail();
        $overrideCandidate = ExpungeCandidate::query()->where('window_id', $window->id)->whereKeyNot($candidate->id)->firstOrFail();
        $block = Block::query()->whereKey($candidate->block_id)->firstOrFail();
        Sanctum::actingAs($this->sg());

        $this->getJson("/api/sg/windows/{$window->id}")
            ->assertOk()
            ->assertJsonPath('window.id', $window->id)
            ->assertJsonCount(2, 'window.expunge_candidates');

        $this->postJson("/api/sg/windows/{$window->id}/open")
            ->assertOk()
            ->assertJsonPath('review.window_id', $window->id);

        $this->postJson("/api/sg/windows/{$window->id}/expunges/{$candidate->id}/confirm")
            ->assertOk()
            ->assertJsonPath('candidate.state', 'confirmed');

        $this->postJson("/api/sg/windows/{$window->id}/expunges/{$overrideCandidate->id}/override", [
            'reason' => 'Context is parliamentary.',
        ])->assertOk()
            ->assertJsonPath('candidate.state', 'overridden');

        $this->postJson("/api/sg/windows/{$window->id}/manual-expunges", [
            'block_id' => $block->id,
            'word' => 'manual phrase',
            'grounds' => 'SG discretionary expunge.',
        ])->assertOk()
            ->assertJsonPath('manual_expunge.word', 'manual phrase');

        $this->getJson("/api/sg/windows/{$window->id}/history")
            ->assertOk()
            ->assertJsonFragment(['action' => 'sg.window.open'])
            ->assertJsonFragment(['action' => 'sg.manual_expunge.add']);

        $this->assertSame(1, SgManualExpunge::query()->where('window_id', $window->id)->count());
        $this->assertAuditActionsChained('on_record', [
            'sg.window.open',
            'sg.expunge.confirm',
            'sg.expunge.override',
            'sg.manual_expunge.add',
        ]);
    }

    public function test_sg_sign_returns_window_to_js_with_dsc_serial(): void
    {
        $window = $this->windowWithCandidates('sent_to_sg');
        Sanctum::actingAs($this->sg());

        $this->postJson("/api/sg/windows/{$window->id}/open")->assertOk();
        $this->postJson("/api/sg/windows/{$window->id}/sign")
            ->assertOk()
            ->assertJsonPath('window.status', 'sg_returned');

        $review = SgReview::query()->where('window_id', $window->id)->firstOrFail();
        $this->assertStringStartsWith('DSC-SG-STUB-', $review->dsc_serial);
        $this->assertAuditActionsChained('on_record', [
            'sg.window.open',
            'sg.window.sign',
        ]);
    }

    private function sg(): User
    {
        return User::query()->where('employee_id', 'SG-001')->firstOrFail();
    }

    private function windowWithCandidates(string $status): JsWindow
    {
        $sitting = Sitting::query()->create([
            'session_no' => 2026,
            'sitting_no' => random_int(1, 9999),
            'sitting_date' => '2026-05-19',
            'status' => 'live',
        ]);
        $slot = Slot::query()->create([
            'sitting_id' => $sitting->id,
            'code' => 'SG1',
            'start_offset_ms' => 0,
            'duration_ms' => 300000,
            'topic' => 'SG workspace test',
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
            'ai_text' => 'bad phrase and disputed phrase',
            'text' => 'bad phrase and disputed phrase',
            'version' => 1,
            'reporter_edit_count' => 0,
        ]);
        $window = JsWindow::query()->create([
            'sitting_id' => $sitting->id,
            'window_code' => 'SG-WIN',
            'starts_at_offset_ms' => 0,
            'duration_ms' => 3600000,
            'status' => $status,
        ]);

        foreach (['bad phrase', 'disputed phrase'] as $index => $word) {
            ExpungeCandidate::query()->create([
                'window_id' => $window->id,
                'block_id' => $block->id,
                'word' => $word,
                'grounds' => 'Test grounds',
                'master_db_ref' => 'MDB-M16-'.$index,
                'state' => 'pending',
            ]);
        }

        return $window;
    }
}
