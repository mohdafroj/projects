<?php

namespace Tests\Feature\Js;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\User;
use App\Modules\Js\Models\ExpungeCandidate;
use App\Modules\Js\Models\JsDecision;
use App\Modules\Js\Models\JsSgHandoff;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Js\Models\SuggestedEdit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JsConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->ensureJsReviewFixtures();
        Sanctum::actingAs($this->jsUser());
    }

    public function test_queue_only_surfaces_windows_with_both_chief_halves_dual_committed(): void
    {
        $window = $this->window();

        $this->getJson('/api/js/queue')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $window->id,
                'window_code' => '1200-1300',
                'chief_halves_count' => 2,
                'suggested_edits_count' => 12,
                'expunge_candidates_count' => 3,
            ]);

        ChiefConsolidation::query()
            ->where('sitting_id', $window->sitting_id)
            ->where('window_code', 'B')
            ->update(['status' => 'open']);

        $this->getJson('/api/js/queue')
            ->assertOk()
            ->assertJsonMissing(['id' => $window->id]);
    }

    public function test_accept_and_decline_suggested_edits_write_audit_and_update_block_text(): void
    {
        $window = $this->window();
        $accepted = SuggestedEdit::query()->where('window_id', $window->id)->where('state', 'pending')->firstOrFail();
        $declined = SuggestedEdit::query()
            ->where('window_id', $window->id)
            ->whereKeyNot($accepted->id)
            ->where('state', 'pending')
            ->firstOrFail();

        $this->postJson("/api/js/windows/{$window->id}/suggested-edits/{$accepted->id}/accept")
            ->assertOk()
            ->assertJsonPath('suggested_edit.state', 'accepted')
            ->assertJsonPath('block.text', $accepted->after);

        $this->assertSame($accepted->after, Block::query()->findOrFail($accepted->block_id)->text);

        $this->postJson("/api/js/windows/{$window->id}/suggested-edits/{$declined->id}/decline", [
            'note' => 'Member submission does not match final source.',
        ])
            ->assertOk()
            ->assertJsonPath('suggested_edit.state', 'declined');

        $this->assertDatabaseHas('audit_logs', ['action' => 'js.se.accept']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'js.se.decline']);
        $this->assertDatabaseHas('js_decisions', [
            'window_id' => $window->id,
            'kind' => 'accept_se',
            'actor_id' => $this->jsUser()->id,
        ]);
        $this->assertDatabaseHas('js_decisions', [
            'window_id' => $window->id,
            'kind' => 'decline_se',
            'actor_id' => $this->jsUser()->id,
        ]);
        $this->assertSame('under_review', $window->fresh()->status);
    }

    public function test_expunge_candidates_surface_and_can_be_decided(): void
    {
        $window = $this->window();
        $candidate = ExpungeCandidate::query()->where('window_id', $window->id)->firstOrFail();
        $overrideCandidate = ExpungeCandidate::query()
            ->where('window_id', $window->id)
            ->whereKeyNot($candidate->id)
            ->firstOrFail();

        $this->getJson("/api/js/windows/{$window->id}/expunge-candidates")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $candidate->id,
                'word' => $candidate->word,
                'state' => 'pending',
            ]);

        $this->postJson("/api/js/windows/{$window->id}/expunge-candidates/{$candidate->id}/confirm")
            ->assertOk()
            ->assertJsonPath('candidate.state', 'confirmed');

        $this->postJson("/api/js/windows/{$window->id}/expunge-candidates/{$overrideCandidate->id}/override")
            ->assertOk()
            ->assertJsonPath('candidate.state', 'overridden');

        $this->assertDatabaseHas('audit_logs', ['action' => 'js.expunge.confirm']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'js.expunge.override']);
        $this->assertSame(2, JsDecision::query()->where('window_id', $window->id)->whereIn('kind', ['expunge_confirm', 'expunge_override'])->count());
    }

    public function test_forward_to_sg_and_simulated_sg_return_transition_window_and_handoff(): void
    {
        $window = $this->window();

        $this->postJson("/api/js/windows/{$window->id}/forward-sg", ['note' => 'Ready for SG review.'])
            ->assertOk()
            ->assertJsonPath('window.status', 'sent_to_sg');

        $handoff = JsSgHandoff::query()->where('window_id', $window->id)->firstOrFail();
        $this->assertNotNull($handoff->sent_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'js.window.forward_sg']);

        $this->postJson("/api/js/windows/{$window->id}/sg-return-simulate")
            ->assertOk()
            ->assertJsonPath('window.status', 'sg_returned');

        $this->assertSame('DSC-SG-SIM-2026', $handoff->fresh()->dsc_serial);
        $this->assertNotNull($handoff->fresh()->returned_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'js.window.sg_return']);
    }

    public function test_approve_publish_marks_window_handed_off_and_updates_chief_halves(): void
    {
        $window = $this->window();
        $window->forceFill(['status' => 'sg_returned'])->save();

        $this->postJson("/api/js/windows/{$window->id}/approve-publish")
            ->assertOk()
            ->assertJsonPath('window.status', 'approved')
            ->assertJsonStructure(['audit_log_id']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'js.window.approve']);
        $this->assertSame('approved', $window->fresh()->status);
        $this->assertSame(2, $window->bothChiefHalves()->where('status', 'forwarded_to_js')->count());
    }

    public function test_return_to_chief_reopens_upstream_chief_halves_and_audit_chain_stays_clean(): void
    {
        $window = $this->window();

        $this->postJson("/api/js/windows/{$window->id}/return", [
            'reason' => 'Chief consolidation needs source recheck.',
            'to_stage' => 'chief',
        ])
            ->assertOk()
            ->assertJsonPath('window.status', 'open');

        $this->assertSame(2, $window->bothChiefHalves()->where('status', 'open')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'js.window.return']);

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    private function window(): JsWindow
    {
        return JsWindow::query()->where('window_code', '1200-1300')->firstOrFail();
    }

    private function jsUser(): User
    {
        return User::query()->where('employee_id', 'JS-001')->firstOrFail();
    }

    private function ensureJsReviewFixtures(): void
    {
        $window = $this->window();
        $blocks = $window->blocks()->limit(12)->get();

        if ($blocks->isEmpty()) {
            $this->fail('The JS demo window must contain transcript blocks.');
        }

        if (SuggestedEdit::query()->where('window_id', $window->id)->count() === 0) {
            foreach (range(1, 12) as $number) {
                $block = $this->blockAt($blocks, $number - 1);

                SuggestedEdit::query()->create([
                    'window_id' => $window->id,
                    'source' => $number % 3 === 0 ? 'ai' : ($number % 2 === 0 ? 'minister' : 'member'),
                    'source_name' => 'JS fixture source '.$number,
                    'block_id' => $block->id,
                    'before' => $block->text,
                    'after' => $block->text.' JS correction '.$number,
                    'reason' => 'Focused JS console test correction '.$number,
                    'state' => 'pending',
                ]);
            }
        }

        if (ExpungeCandidate::query()->where('window_id', $window->id)->count() === 0) {
            foreach (range(1, 3) as $number) {
                $block = $this->blockAt($blocks, $number - 1);

                ExpungeCandidate::query()->create([
                    'window_id' => $window->id,
                    'block_id' => $block->id,
                    'word' => 'fixture phrase '.$number,
                    'grounds' => 'Focused JS console expunge candidate '.$number,
                    'master_db_ref' => 'MDB-JS-FIXTURE-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    'state' => 'pending',
                ]);
            }
        }
    }

    /**
     * @param Collection<int, Block> $blocks
     */
    private function blockAt(Collection $blocks, int $index): Block
    {
        return $blocks->values()->get($index % $blocks->count());
    }
}
