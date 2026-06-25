<?php

namespace Tests\Feature;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SynopsisWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Role::firstOrCreate(['name' => 'synopsis_writer', 'guard_name' => 'web']);
        config()->set('services.synopsis.allowed_hosts', ['ml-gateway', 'hosted-model.test']);
    }

    public function test_synopsis_workspace_routes_render_shell(): void
    {
        $consolidation = $this->acceptedConsolidation();

        $this->get('/app/synopsis')
            ->assertOk()
            ->assertSee('data-workspace="synopsis"', false)
            ->assertSee('Synopsis Writer Workspace');

        $this->get("/app/synopsis/chunks/{$consolidation->id}")
            ->assertOk()
            ->assertSee('data-initial-consolidation="'.$consolidation->id.'"', false);
    }

    public function test_synopsis_writer_login_queue_and_hosted_generation_contract(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = 'Proceedings: Workspace text should produce a formal and attributed synopsis from the hosted E&T model endpoint.';
        $sourceHash = hash('sha256', $sourceText);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => fn ($request) => Http::response([
                'title' => 'Hosted Workspace Synopsis',
                'body' => "Hosted Workspace Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Workspace text was summarised by the hosted E&T model.\n\nAttribution Notes\n- Proceedings: source excerpt - Workspace text.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'request_id' => $request['request_id'],
                'source_sha256' => $sourceHash,
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Workspace text was summarised by the hosted E&T model.',
                ]],
            ], 200),
            'https://api.sarvam.ai/*' => Http::response(['message' => 'must not call Sarvam'], 500),
        ]);
        $consolidation = $this->acceptedConsolidation();
        $writer = $this->writer();

        $login = $this->postJson('/api/auth/login', [
            'employee_id' => $writer->employee_id,
            'password' => 'chief123',
        ])->assertOk();
        $this->assertContains('synopsis_writer', $login->json('roles'));

        $this->withToken($login->json('token'))
            ->getJson('/api/synopsis/queue')
            ->assertOk()
            ->assertJsonFragment(['id' => $consolidation->id, 'chunk_code' => $consolidation->window_code]);

        $this->withToken($login->json('token'))
            ->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
                'source_text' => $sourceText,
            ])
            ->assertOk()
            ->assertJsonPath('document.title', 'Hosted Workspace Synopsis')
            ->assertJsonPath('document.attributions.0.summary_text', 'Workspace text was summarised by the hosted E&T model.');

        Http::assertSent(fn ($request) => $request->url() === 'http://hosted-model.test/v1/synopsis'
            && $request['task'] === 'parliamentary_synopsis'
            && $request['source']['sha256'] === $sourceHash);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sarvam.ai'));
    }

    public function test_synopsis_workspace_distinguishes_source_conflicts_from_version_conflicts(): void
    {
        $workspaceJs = file_get_contents(base_path('resources/js/synopsis-workspace.js'));

        $this->assertStringContainsString('generated_source_sha256', $workspaceJs);
        $this->assertStringContainsString('Source blocks changed after synopsis generation. Regenerate before submit or finalise.', $workspaceJs);
        $this->assertStringContainsString('Version conflict. Current saved version is', $workspaceJs);
        $this->assertStringContainsString('reloadActiveChunk', $workspaceJs);
        $this->assertStringContainsString('applyDraftConflictPayload', $workspaceJs);
        $this->assertStringContainsString('current_title', $workspaceJs);
        $this->assertStringContainsString('current_attributions', $workspaceJs);
    }

    public function test_synopsis_workspace_normalises_draft_payload_before_save(): void
    {
        $workspaceJs = file_get_contents(base_path('resources/js/synopsis-workspace.js'));

        $this->assertStringContainsString("form.querySelector('[name=\"title\"]').value.trim()", $workspaceJs);
        $this->assertStringContainsString("form.querySelector('[name=\"body\"]').value.trim()", $workspaceJs);
        $this->assertStringContainsString("form.querySelector('[name=\"source_text\"]').value.trim()", $workspaceJs);
        $this->assertStringContainsString("row.querySelector('[name=\"speaker_name\"]').value.trim()", $workspaceJs);
        $this->assertStringContainsString("row.querySelector('[name=\"constituency\"]').value.trim() || null", $workspaceJs);
        $this->assertStringContainsString("row.querySelector('[name=\"summary_text\"]').value.trim()", $workspaceJs);
    }

    public function test_synopsis_workspace_locks_non_ready_submitted_and_final_states(): void
    {
        $workspaceJs = file_get_contents(base_path('resources/js/synopsis-workspace.js'));

        $this->assertStringContainsString("['dual_committed', 'forwarded_to_js'].includes(chunk.source_status)", $workspaceJs);
        $this->assertStringContainsString('Chief consolidation must be accepted before synopsis drafting.', $workspaceJs);
        $this->assertStringContainsString("document.status === 'submitted' || final || sourceLocked", $workspaceJs);
        $this->assertStringContainsString("document.status === 'draft' && sourceReady", $workspaceJs);
        $this->assertStringContainsString("document.status === 'submitted' && sourceReady", $workspaceJs);
        $this->assertStringContainsString("document.status === 'final' ? '' : 'disabled'", $workspaceJs);
        $this->assertStringContainsString('readonly', $workspaceJs);
    }

    public function test_synopsis_workspace_renders_hosted_history_request_evidence(): void
    {
        $workspaceJs = file_get_contents(base_path('resources/js/synopsis-workspace.js'));

        $this->assertStringContainsString('generation_fallback_detail', $workspaceJs);
        $this->assertStringContainsString('generation_http_status', $workspaceJs);
        $this->assertStringContainsString('HTTP ${evidence.generation_http_status}', $workspaceJs);
        $this->assertStringContainsString('generation_request_id', $workspaceJs);
        $this->assertStringContainsString('req ${shortHash(evidence.generation_request_id)}', $workspaceJs);
    }

    public function test_non_writer_cannot_access_synopsis_api(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $this->getJson('/api/synopsis/queue')->assertForbidden();
    }

    private function writer(): User
    {
        $writer = User::query()->where('employee_id', 'CHF-EN-001')->firstOrFail();
        $writer->assignRole('synopsis_writer');

        return $writer;
    }

    private function acceptedConsolidation(): ChiefConsolidation
    {
        $consolidation = ChiefConsolidation::query()->where('window_code', 'A')->firstOrFail();
        $consolidation->forceFill(['status' => 'dual_committed'])->save();

        return $consolidation;
    }
}
