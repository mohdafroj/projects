<?php

namespace Tests\Feature\Sg;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use App\Modules\Js\Models\ExpungeCandidate;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Sg\Models\SgManualExpunge;
use App\Modules\Sg\Models\SgReview;
use App\Modules\Sg\Services\DscAdapter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SgDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_sg_open_confirm_override_manual_and_sign_cycle(): void
    {
        $sg = User::query()->where('employee_id', 'SG-001')->firstOrFail();
        $window = $this->windowWithCandidates('sent_to_sg');
        $candidate = ExpungeCandidate::query()->where('window_id', $window->id)->firstOrFail();
        $overrideCandidate = ExpungeCandidate::query()->where('window_id', $window->id)->whereKeyNot($candidate->id)->firstOrFail();
        $block = Block::query()->whereKey($candidate->block_id)->firstOrFail();
        Sanctum::actingAs($sg);

        $this->getJson('/api/sg/tray')
            ->assertOk()
            ->assertJsonFragment(['id' => $window->id, 'status' => 'sent_to_sg']);

        $this->postJson("/api/sg/windows/{$window->id}/open")
            ->assertOk()
            ->assertJsonPath('review.window_id', $window->id);

        $this->postJson("/api/sg/windows/{$window->id}/expunges/{$candidate->id}/confirm")
            ->assertOk()
            ->assertJsonPath('candidate.state', 'confirmed');

        $this->postJson("/api/sg/windows/{$window->id}/expunges/{$overrideCandidate->id}/override", ['reason' => 'Context is parliamentary.'])
            ->assertOk()
            ->assertJsonPath('candidate.state', 'overridden');

        $this->postJson("/api/sg/windows/{$window->id}/manual-expunges", [
            'block_id' => $block->id,
            'word' => 'manual phrase',
            'grounds' => 'SG discretionary expunge.',
        ])->assertOk()
            ->assertJsonPath('manual_expunge.word', 'manual phrase');

        $this->postJson("/api/sg/windows/{$window->id}/sign")
            ->assertOk()
            ->assertJsonPath('window.status', 'sg_returned')
            ->assertJsonPath('review.confirmed_expunges', 1)
            ->assertJsonPath('review.overridden_expunges', 1)
            ->assertJsonPath('review.manual_expunges', 1);

        $review = SgReview::query()->where('window_id', $window->id)->firstOrFail();
        $this->assertStringStartsWith('DSC-SG-STUB-', $review->dsc_serial);
        $this->assertSame('sg_returned', $window->fresh()->status);
        $this->assertSame(1, SgManualExpunge::query()->where('window_id', $window->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'sg.window.open']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sg.expunge.confirm']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sg.expunge.override']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sg.manual_expunge.add']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sg.window.sign']);
    }

    public function test_dsc_stub_returns_synthetic_serial(): void
    {
        $window = $this->windowWithCandidates('sent_to_sg');

        $signature = app(DscAdapter::class)->sign($window);

        $this->assertStringStartsWith('DSC-SG-STUB-', $signature['serial']);
        $this->assertNotNull($signature['signed_at']);
    }

    public function test_sg_cycle_leaves_audit_chain_clean(): void
    {
        $sg = User::query()->where('employee_id', 'SG-001')->firstOrFail();
        $window = $this->windowWithCandidates('sent_to_sg');
        Sanctum::actingAs($sg);

        $this->postJson("/api/sg/windows/{$window->id}/open")->assertOk();
        $this->postJson("/api/sg/windows/{$window->id}/sign")->assertOk();

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    private function windowWithCandidates(string $status): JsWindow
    {
        $sitting = Sitting::query()->create([
            'session_no' => 2026,
            'sitting_no' => 1,
            'sitting_date' => '2026-05-19',
            'status' => 'live',
        ]);
        $slot = Slot::query()->create([
            'sitting_id' => $sitting->id,
            'code' => 'T1',
            'start_offset_ms' => 0,
            'duration_ms' => 300000,
            'topic' => 'Test',
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
            'window_code' => '1200-1300',
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
                'master_db_ref' => 'MDB-TEST-'.$index,
                'state' => 'pending',
            ]);
        }

        return $window;
    }
}
