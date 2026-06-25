<?php

namespace Tests\Feature\Synopsis;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use App\Modules\Synopsis\Models\SynopsisDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SynopsisStudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Role::firstOrCreate(['name' => 'synopsis_writer', 'guard_name' => 'web']);
        config()->set('services.synopsis.allowed_hosts', ['ml-gateway', 'hosted-model.test']);
    }

    public function test_writer_generates_refines_submits_finalises_and_exports_synopsis(): void
    {
        $writer = $this->writer();
        $consolidation = $this->acceptedConsolidation();
        Sanctum::actingAs($writer);

        $this->getJson('/api/synopsis/queue')
            ->assertOk()
            ->assertJsonFragment(['chunk_code' => $consolidation->window_code, 'status' => 'empty']);

        $generate = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")
            ->assertOk()
            ->assertJsonPath('document.status', 'draft')
            ->assertJsonPath('document.source_mode', 'ai')
            ->assertJsonPath('document.ai_first_draft', true);

        $version = $generate->json('document.version');

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'title' => 'Same-day Synopsis Chunk A',
            'body' => "Shri R. Patil (Karnataka): Raised National Highway 75 expansion delays.\n\nProceedings: Submissions were taken on record.",
            'version' => $version,
            'attributions' => [
                [
                    'speaker_name' => 'Shri R. Patil',
                    'constituency' => 'Karnataka',
                    'summary_text' => 'Raised National Highway 75 expansion delays.',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('document.status', 'draft')
            ->assertJsonPath('document.title', 'Same-day Synopsis Chunk A');

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/submit")
            ->assertOk()
            ->assertJsonPath('document.status', 'submitted');

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/finalise")
            ->assertOk()
            ->assertJsonPath('document.status', 'final')
            ->assertJsonPath('document.ai_first_draft', false);

        $export = $this->get("/api/synopsis/chunks/{$consolidation->id}/export.pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertSee('%PDF-1.4', false);
        $this->assertSame(hash('sha256', $export->getContent()), $export->headers->get('X-Vani-Setu-Pdf-Sha256'));

        $this->assertDatabaseHas('synopsis_document_edits', ['kind' => 'generate']);
        $this->assertDatabaseHas('synopsis_document_edits', ['kind' => 'save']);
        $this->assertDatabaseHas('synopsis_document_edits', ['kind' => 'submit']);
        $this->assertDatabaseHas('synopsis_document_edits', ['kind' => 'finalise']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'synopsis.draft.generated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'synopsis.draft.save']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'synopsis.draft.submit']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'synopsis.finalise']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'synopsis.pdf.export']);
        $exportAudit = AuditLog::query()->where('action', 'synopsis.pdf.export')->latest('id')->firstOrFail();
        $this->assertSame(hash('sha256', $export->getContent()), $exportAudit->payload['pdf_sha256']);
        $this->assertSame(strlen($export->getContent()), $exportAudit->payload['pdf_bytes']);

        $this->getJson("/api/synopsis/chunks/{$consolidation->id}/history")
            ->assertOk()
            ->assertJsonPath('audit_events.0.action', 'synopsis.pdf.export')
            ->assertJsonPath('audit_events.0.audit_evidence.pdf_sha256', hash('sha256', $export->getContent()))
            ->assertJsonPath('audit_events.0.audit_evidence.pdf_bytes', strlen($export->getContent()))
            ->assertJsonMissingPath('audit_events.0.payload');
    }

    public function test_submit_and_finalise_audits_preserve_generation_evidence(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        config()->set('services.synopsis.model', 'vani-setu-synopsis-local');
        $consolidation = $this->acceptedConsolidation();
        $sourceText = "Proceedings: Evidence text for submit and finalise audit preservation.\n\nThe Minister: The source hash and hosted generation metadata should carry forward.";
        $sourceHash = hash('sha256', $sourceText);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => fn ($request) => Http::response([
                'title' => 'Hosted Evidence Synopsis',
                'body' => "Hosted Evidence Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\nGeneration Model: vani-setu-synopsis-local\n\nSynopsis\n- Proceedings: Hosted evidence draft.\n\nAttribution Notes\n- Proceedings: source excerpt - Evidence text.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'request_id' => $request['request_id'],
                'source_sha256' => $sourceHash,
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Hosted evidence draft.',
                ]],
            ], 200),
            'https://api.sarvam.ai/*' => Http::response(['message' => 'must not call Sarvam'], 500),
        ]);
        Sanctum::actingAs($this->writer());

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/submit")->assertOk();
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/finalise")->assertOk();

        $submitAudit = AuditLog::query()->where('action', 'synopsis.draft.submit')->latest('id')->firstOrFail();
        $this->assertSame($sourceHash, $submitAudit->payload['source_sha256']);
        $this->assertSame('hosted_model', $submitAudit->payload['generation']['provider']);
        $this->assertSame('vani-setu-synopsis-local', $submitAudit->payload['generation']['model']);

        $finaliseAudit = AuditLog::query()->where('action', 'synopsis.finalise')->latest('id')->firstOrFail();
        $this->assertSame($sourceHash, $finaliseAudit->payload['source_sha256']);
        $this->assertSame('hosted_model', $finaliseAudit->payload['generation']['provider']);
        $this->assertSame('vani-setu-synopsis-local', $finaliseAudit->payload['generation']['model']);
    }

    public function test_non_writer_cannot_open_synopsis_studio(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $this->getJson('/api/synopsis/queue')->assertForbidden();
    }

    public function test_source_must_be_accepted_before_drafting(): void
    {
        Sanctum::actingAs($this->writer());
        $consolidation = ChiefConsolidation::query()->where('window_code', 'A')->firstOrFail();
        $consolidation->forceFill(['status' => 'open'])->save();

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/author")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Chief consolidation must be accepted before synopsis drafting.');
    }

    public function test_source_must_remain_accepted_before_submit_and_finalise(): void
    {
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")->assertOk();

        $consolidation->forceFill(['status' => 'open'])->save();
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/submit")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Chief consolidation must be accepted before synopsis drafting.');

        $consolidation->forceFill(['status' => 'dual_committed'])->save();
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/submit")->assertOk();

        $consolidation->forceFill(['status' => 'open'])->save();
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/finalise")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Chief consolidation must be accepted before synopsis drafting.');
    }

    public function test_block_generated_synopsis_requires_current_source_fingerprint_before_submit_and_finalise(): void
    {
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $generate = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")
            ->assertOk();
        $generatedHash = $generate->json('document.latest_generation.source_sha256');

        $this->mutateFirstSourceBlock($consolidation, 'Chief source changed before synopsis submit.');
        $currentHash = $this->consolidationSourceHash($consolidation->fresh());

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/submit")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Source blocks changed after synopsis generation. Regenerate before submit or finalise.')
            ->assertJsonPath('generated_source_sha256', $generatedHash)
            ->assertJsonPath('source_sha256', $currentHash);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'synopsis.draft.submit']);

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")
            ->assertOk()
            ->assertJsonPath('document.latest_generation.source_sha256', $currentHash);
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/submit")
            ->assertOk()
            ->assertJsonPath('document.status', 'submitted');

        $this->mutateFirstSourceBlock($consolidation, 'Chief source changed before synopsis finalise.');
        $finaliseCurrentHash = $this->consolidationSourceHash($consolidation->fresh());

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/finalise")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Source blocks changed after synopsis generation. Regenerate before submit or finalise.')
            ->assertJsonPath('generated_source_sha256', $currentHash)
            ->assertJsonPath('source_sha256', $finaliseCurrentHash);
        $this->assertSame('submitted', SynopsisDocument::query()
            ->where('consolidation_id', $consolidation->id)
            ->value('status'));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'synopsis.finalise']);
    }

    public function test_generation_rechecks_source_readiness_after_hosted_model_call(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => function () {
                ChiefConsolidation::query()
                    ->where('window_code', 'A')
                    ->update(['status' => 'open']);

                return Http::response([
                    'title' => 'Late Hosted Synopsis',
                    'body' => "Late Hosted Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: This should not be persisted after source readiness changes.\n\nAttribution Notes\n- Proceedings: source excerpt - readiness changed.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                    'attributions' => [],
                ], 200);
            },
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => "Proceedings: Hosted model call starts while the source is ready.\n\nThe source readiness changes before the generated body can be written.",
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Chief consolidation must be accepted before synopsis drafting.');

        $document = SynopsisDocument::query()->where('consolidation_id', $consolidation->id)->firstOrFail();
        $this->assertSame('empty', $document->status);
        $this->assertNull($document->body);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'synopsis.draft.generated_from_text']);
    }

    public function test_block_generation_rechecks_source_fingerprint_after_hosted_model_call(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();
        $generatedHash = $this->consolidationSourceHash($consolidation);

        Http::fake([
            'http://hosted-model.test/v1/synopsis' => function () use ($consolidation, $generatedHash) {
                $this->mutateFirstSourceBlock($consolidation, 'Chief source changed during hosted block synopsis generation.');

                return Http::response([
                    'title' => 'Late Block Hosted Synopsis',
                    'body' => "Late Block Hosted Synopsis\n\nSource Notes\nSource: Approved Chief consolidation\n\nSynopsis\n- Proceedings: This stale block draft should not be persisted.\n\nAttribution Notes\n- Proceedings: source excerpt - Changed during generation.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                    'source_sha256' => $generatedHash,
                    'attributions' => [[
                        'speaker_name' => 'Proceedings',
                        'constituency' => null,
                        'summary_text' => 'This stale block draft should not be persisted.',
                    ]],
                ], 200);
            },
        ]);

        $currentHash = null;
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Source blocks changed after synopsis generation. Regenerate before submit or finalise.')
            ->assertJsonPath('generated_source_sha256', $generatedHash)
            ->tap(function ($response) use (&$currentHash): void {
                $currentHash = $response->json('source_sha256');
                $this->assertNotEmpty($currentHash);
            });

        $this->assertNotSame($generatedHash, $currentHash);
        $document = SynopsisDocument::query()->where('consolidation_id', $consolidation->id)->firstOrFail();
        $this->assertSame('empty', $document->status);
        $this->assertNull($document->body);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'synopsis.draft.generated']);
    }

    public function test_writer_can_generate_polished_synopsis_from_pasted_long_text(): void
    {
        $writer = $this->writer();
        $consolidation = $this->acceptedConsolidation();
        Sanctum::actingAs($writer);

        $sourceText = "Shri R. Patil (Karnataka): Raised delays in National Highway 75 expansion and requested a time-bound statement from the Ministry. He submitted that land acquisition and contractor mobilisation require close monitoring.\n\nThe Minister: Stated that the Ministry has reviewed the matter with the State Government and that revised milestones will be shared with Members after verification.";
        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'title' => 'Same-day Synopsis - Pasted Text',
            'source_text' => $sourceText,
        ])
            ->assertOk()
            ->assertJsonPath('document.status', 'draft')
            ->assertJsonPath('document.source_mode', 'ai')
            ->assertJsonPath('document.ai_first_draft', true)
            ->assertJsonPath('document.version', 2)
            ->assertJsonPath('document.title', 'Same-day Synopsis - Pasted Text')
            ->assertJsonPath('document.latest_generation.provider', 'fallback')
            ->assertJsonPath('document.latest_generation.fallback_reason', 'endpoint_not_configured')
            ->assertJsonPath('document.latest_generation.source_sha256', hash('sha256', $sourceText));

        $body = (string) $response->json('document.body');
        $this->assertStringContainsString('Source Notes', $body);
        $this->assertStringContainsString('Source: Writer pasted proceedings text', $body);
        $this->assertStringContainsString('Synopsis', $body);
        $this->assertStringContainsString('Shri R. Patil (Karnataka): Raised delays in National Highway 75 expansion', $body);
        $this->assertStringContainsString('Attribution Notes', $body);
        $this->assertStringContainsString('Editorial Status: First draft by AI; writer review and final commit required before publication.', $body);

        $this->assertSame('Shri R. Patil', $response->json('document.attributions.0.speaker_name'));
        $this->assertSame('Karnataka', $response->json('document.attributions.0.constituency'));
        $this->assertDatabaseHas('synopsis_document_edits', ['kind' => 'generate', 'from_version' => 1, 'to_version' => 2]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'synopsis.draft.generated_from_text']);
        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame(hash('sha256', $sourceText), $audit->payload['source_sha256']);
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('vani-setu-synopsis', $audit->payload['generation']['model']);
        $this->assertSame('endpoint_not_configured', $audit->payload['generation']['fallback_reason']);
        $this->getJson('/api/synopsis/queue')
            ->assertOk()
            ->assertJsonPath('0.latest_generation.provider', 'fallback')
            ->assertJsonPath('0.latest_generation.source_sha256', hash('sha256', $sourceText));
    }

    public function test_generate_from_text_rejects_whitespace_only_meaningless_source(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted draft should not be requested',
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => str_repeat(' ', 80),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('source_text');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'synopsis.draft.generated_from_text']);
        $this->assertDatabaseMissing('synopsis_documents', [
            'consolidation_id' => $consolidation->id,
        ]);
    }

    public function test_generate_from_text_trims_source_before_hashing_and_hosted_request(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = 'Proceedings: Trimmed source text should define the hosted synopsis request and stored source hash.';
        $sourceHash = hash('sha256', $sourceText);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => fn ($request) => Http::response([
                'title' => 'Trimmed Hosted Synopsis',
                'body' => "Trimmed Hosted Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Trimmed source was accepted.\n\nAttribution Notes\n- Proceedings: source excerpt - Trimmed source text.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'request_id' => $request['request_id'],
                'source_sha256' => $sourceHash,
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Trimmed source was accepted.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => "\n\n  {$sourceText}  \n",
            'title' => '   ',
        ])
            ->assertOk()
            ->assertJsonPath('document.title', 'Trimmed Hosted Synopsis')
            ->assertJsonPath('document.latest_generation.source_sha256', $sourceHash);

        Http::assertSent(fn ($request) => $request['source']['text'] === $sourceText
            && $request['source']['sha256'] === $sourceHash);
        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame($sourceHash, $audit->payload['source_sha256']);
        $this->assertSame(mb_strlen($sourceText), $audit->payload['source_characters']);
    }

    public function test_synopsis_uses_hosted_model_endpoint_not_sarvam(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        config()->set('services.synopsis.model', 'vani-setu-synopsis-local');
        config()->set('services.synopsis.token', 'internal-token');
        $consolidation = $this->acceptedConsolidation();
        $sourceText = "Shri R. Patil (Karnataka): Raised delays in National Highway 75 expansion and requested a time-bound statement from the Ministry.\n\nThe Minister: Stated that revised milestones will be shared with Members after verification.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => fn ($request) => Http::response([
                'title' => 'Hosted Model Synopsis',
                'body' => "Hosted Model Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\nGeneration Model: vani-setu-synopsis-local\n\nSynopsis\n- Shri R. Patil (Karnataka): Hosted model summarised the highway expansion issue.\n\nAttribution Notes\n- Shri R. Patil (Karnataka): source excerpt - Raised delays in highway expansion.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'request_id' => $request['request_id'],
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Shri R. Patil',
                    'constituency' => 'Karnataka',
                    'summary_text' => 'Hosted model summarised the highway expansion issue.',
                ]],
            ], 200),
            'https://api.sarvam.ai/*' => Http::response(['message' => 'must not call Sarvam'], 500),
        ]);
        Sanctum::actingAs($this->writer());

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])
            ->assertOk()
            ->assertJsonPath('document.title', 'Hosted Model Synopsis')
            ->assertJsonPath('document.latest_generation.provider', 'hosted_model')
            ->assertJsonPath('document.latest_generation.model', 'vani-setu-synopsis-local')
            ->assertJsonPath('document.latest_generation.source_sha256', hash('sha256', $sourceText))
            ->assertJsonPath('document.attributions.0.summary_text', 'Hosted model summarised the highway expansion issue.');

        $requestIds = [];
        Http::assertSent(function ($request) use (&$requestIds, $consolidation, $sourceText): bool {
            $requestId = $request->header('X-Vani-Setu-Request-Id')[0] ?? null;
            if (! filled($requestId)) {
                return false;
            }

            $requestIds[] = $requestId;

            return $request->url() === 'http://hosted-model.test/v1/synopsis'
                && $request->hasHeader('Authorization', 'Bearer internal-token')
                && $request->hasHeader('X-Vani-Setu-Module', 'synopsis')
                && $request->hasHeader('X-Vani-Setu-Chunk', $consolidation->window_code)
                && $request->hasHeader('X-Vani-Setu-Model', 'vani-setu-synopsis-local')
                && $request->hasHeader('X-Vani-Setu-Source-Sha256', hash('sha256', $sourceText))
                && $request['request_id'] === $requestId
                && $request['model'] === 'vani-setu-synopsis-local'
                && $request['task'] === 'parliamentary_synopsis'
                && $request['source']['label'] === 'Writer pasted proceedings text'
                && $request['source']['sha256'] === hash('sha256', $sourceText);
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sarvam.ai'));
        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame(hash('sha256', $sourceText), $audit->payload['source_sha256']);
        $this->assertSame('hosted_model', $audit->payload['generation']['provider']);
        $this->assertSame('vani-setu-synopsis-local', $audit->payload['generation']['model']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
        $this->assertSame($requestIds[0], $audit->payload['generation']['request_id']);
    }

    public function test_hosted_model_source_hash_mismatch_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Mismatched Hosted Synopsis',
                'body' => "Mismatched Hosted Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: This should not be accepted for the wrong source hash.\n\nAttribution Notes\n- Proceedings: source excerpt - Wrong source.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => str_repeat('0', 64),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Mismatched hosted draft.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();
        $sourceText = "Shri R. Patil (Karnataka): Raised delays in National Highway 75 expansion and requested a time-bound statement from the Ministry.\n\nThe Minister: Stated that revised milestones will be shared with Members after verification.";

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_source_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Mismatched Hosted Synopsis', (string) $response->json('document.body'));
        $this->assertSame(hash('sha256', $sourceText), $response->json('document.latest_generation.source_sha256'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame(hash('sha256', $sourceText), $audit->payload['source_sha256']);
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('hosted_model_source_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
        $this->assertNotEmpty($audit->payload['generation']['request_id']);
    }

    public function test_hosted_model_non_string_source_hash_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted source hashes must be strings.\n\nThe Minister: Array hashes must not be coerced into envelope checks.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Typed Source Hash',
                'body' => "Hosted Draft With Typed Source Hash\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Typed source hash should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Typed source hash.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => [hash('sha256', $sourceText)],
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Typed source hash should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_source_hash', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Typed Source Hash', (string) $response->json('document.body'));
        $this->assertSame(hash('sha256', $sourceText), $response->json('document.latest_generation.source_sha256'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame(hash('sha256', $sourceText), $audit->payload['source_sha256']);
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('hosted_model_invalid_source_hash', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_conflicting_source_hash_aliases_fall_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted source hash aliases must agree when both are returned.\n\nThe Minister: Conflicting aliases should not be accepted as provenance evidence.";
        $sourceHash = hash('sha256', $sourceText);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Conflicting Source Hashes',
                'body' => "Hosted Draft With Conflicting Source Hashes\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Conflicting source aliases should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Conflicting aliases.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => $sourceHash,
                'source' => ['sha256' => str_repeat('0', 64)],
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Conflicting source aliases should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_source_hash', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Conflicting Source Hashes', (string) $response->json('document.body'));
        $this->assertSame($sourceHash, $response->json('document.latest_generation.source_sha256'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame($sourceHash, $audit->payload['source_sha256']);
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('hosted_model_invalid_source_hash', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_request_id_mismatch_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Request id correlation should prevent accepting a stale hosted response.\n\nThe Minister: Confirmed the source hash alone must not hide a mismatched response envelope.";
        $sourceHash = hash('sha256', $sourceText);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'request_id' => 'stale-request-id',
                'title' => 'Stale Hosted Synopsis',
                'body' => "Stale Hosted Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: This should not be accepted for a mismatched request id.\n\nAttribution Notes\n- Proceedings: source excerpt - Request id correlation.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => $sourceHash,
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Request id correlation was mismatched.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_request_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Stale Hosted Synopsis', (string) $response->json('document.body'));
        $this->assertSame($sourceHash, $response->json('document.latest_generation.source_sha256'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame($sourceHash, $audit->payload['source_sha256']);
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('hosted_model_request_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertNotSame('stale-request-id', $audit->payload['generation']['request_id']);
        $this->assertNotEmpty($audit->payload['generation']['request_id']);
    }

    public function test_hosted_model_missing_request_id_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted responses must echo the request id.\n\nThe Minister: Responses without request correlation should not be accepted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Uncorrelated Hosted Synopsis',
                'body' => "Uncorrelated Hosted Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Missing request ids should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Request id correlation.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Missing request ids should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_missing_request_id', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Uncorrelated Hosted Synopsis', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_missing_request_id', $audit->payload['generation']['fallback_reason']);
        $this->assertNotEmpty($audit->payload['generation']['request_id']);
    }

    public function test_hosted_model_non_string_request_id_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted request ids must be strings when returned.\n\nThe Minister: Array request ids must be treated as malformed envelopes.";
        $sourceHash = hash('sha256', $sourceText);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'request_id' => ['not-a-string'],
                'title' => 'Hosted Draft With Typed Request Id',
                'body' => "Hosted Draft With Typed Request Id\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Typed request id should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Typed request id.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => $sourceHash,
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Typed request id should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_request_id', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Typed Request Id', (string) $response->json('document.body'));
        $this->assertSame($sourceHash, $response->json('document.latest_generation.source_sha256'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame($sourceHash, $audit->payload['source_sha256']);
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('hosted_model_invalid_request_id', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
        $this->assertNotEmpty($audit->payload['generation']['request_id']);
    }

    public function test_hosted_model_conflicting_request_id_aliases_fall_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted request id aliases must agree when both are returned.\n\nThe Minister: Conflicting request aliases should not be accepted as current response evidence.";
        $sourceHash = hash('sha256', $sourceText);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'request_id' => 'stale-primary-id',
                'meta' => ['request_id' => 'different-stale-id'],
                'title' => 'Hosted Draft With Conflicting Request Ids',
                'body' => "Hosted Draft With Conflicting Request Ids\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Conflicting request aliases should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Conflicting aliases.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => $sourceHash,
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Conflicting request aliases should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_request_id', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Conflicting Request Ids', (string) $response->json('document.body'));
        $this->assertSame($sourceHash, $response->json('document.latest_generation.source_sha256'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame($sourceHash, $audit->payload['source_sha256']);
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('hosted_model_invalid_request_id', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
        $this->assertNotEmpty($audit->payload['generation']['request_id']);
    }

    public function test_hosted_model_accepts_nested_source_hash_alias(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Nested response source hash aliases should still prove the hosted draft belongs to this input.\n\nThe Minister: Confirmed provenance validation is mandatory.";
        $sourceHash = hash('sha256', $sourceText);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => fn ($request) => Http::response([
                'title' => 'Nested Hash Hosted Synopsis',
                'body' => "Nested Hash Hosted Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Nested hash alias was accepted for this hosted draft.\n\nAttribution Notes\n- Proceedings: source excerpt - Nested response source hash aliases.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'request_id' => $request['request_id'],
                'source' => ['sha256' => $sourceHash],
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Nested hash alias was accepted for this hosted draft.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])
            ->assertOk()
            ->assertJsonPath('document.title', 'Nested Hash Hosted Synopsis')
            ->assertJsonPath('document.latest_generation.provider', 'hosted_model')
            ->assertJsonPath('document.latest_generation.source_sha256', $sourceHash);

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame($sourceHash, $audit->payload['source_sha256']);
        $this->assertSame('hosted_model', $audit->payload['generation']['provider']);
    }

    public function test_synopsis_refuses_sarvam_endpoint_configuration(): void
    {
        config()->set('services.synopsis.model_url', 'https://api.sarvam.ai/v1/synopsis');
        Http::fake([
            'https://api.sarvam.ai/*' => Http::response(['title' => 'Sarvam draft should not be called'], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();
        $sourceText = "Shri R. Patil (Karnataka): Raised delays in National Highway 75 expansion and requested a time-bound statement from the Ministry.\n\nThe Minister: Stated that revised milestones will be shared with Members after verification.";

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])
            ->assertOk()
            ->assertJsonPath('document.latest_generation.provider', 'fallback')
            ->assertJsonPath('document.latest_generation.fallback_reason', 'forbidden_sarvam_endpoint')
            ->assertJsonPath('document.latest_generation.source_sha256', hash('sha256', $sourceText));

        Http::assertNothingSent();
        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('forbidden_sarvam_endpoint', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(hash('sha256', $sourceText), $audit->payload['source_sha256']);
    }

    public function test_synopsis_refuses_invalid_hosted_endpoint_configuration(): void
    {
        config()->set('services.synopsis.model_url', 'ml-gateway:8000/v1/synopsis');
        Http::fake();
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();
        $sourceText = "Shri R. Patil (Karnataka): Raised delays in National Highway 75 expansion and requested a time-bound statement from the Ministry.\n\nThe Minister: Stated that revised milestones will be shared with Members after verification.";

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])
            ->assertOk()
            ->assertJsonPath('document.latest_generation.provider', 'fallback')
            ->assertJsonPath('document.latest_generation.fallback_reason', 'invalid_hosted_endpoint')
            ->assertJsonPath('document.latest_generation.source_sha256', hash('sha256', $sourceText));

        Http::assertNothingSent();
        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('invalid_hosted_endpoint', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(hash('sha256', $sourceText), $audit->payload['source_sha256']);
    }

    public function test_synopsis_refuses_non_inhouse_hosted_endpoint_configuration(): void
    {
        config()->set('services.synopsis.model_url', 'https://example.com/v1/synopsis');
        config()->set('services.synopsis.allowed_hosts', ['ml-gateway', 'hosted-model.test']);
        Http::fake([
            'https://example.com/*' => Http::response(['message' => 'must not call external host'], 500),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();
        $sourceText = "Proceedings: Synopsis must use the in-house E&T hosted model only.\n\nThe Minister: Arbitrary external hosts should not receive source text.";

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])
            ->assertOk()
            ->assertJsonPath('document.latest_generation.provider', 'fallback')
            ->assertJsonPath('document.latest_generation.fallback_reason', 'non_inhouse_hosted_endpoint')
            ->assertJsonPath('document.latest_generation.source_sha256', hash('sha256', $sourceText));

        Http::assertNothingSent();
        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('non_inhouse_hosted_endpoint', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(hash('sha256', $sourceText), $audit->payload['source_sha256']);
    }

    public function test_block_based_generation_sends_and_audits_source_fingerprint(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();
        $sourceHash = $this->consolidationSourceHash($consolidation);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => fn ($request) => Http::response([
                'title' => 'Hosted Block Synopsis',
                'body' => "Hosted Block Synopsis\n\nSource Notes\nSource: Approved Chief consolidation\n\nSynopsis\n- Proceedings: Hosted model summarised accepted source blocks.\n\nAttribution Notes\n- Proceedings: source excerpt - Accepted source block.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'request_id' => $request['request_id'],
                'source_sha256' => $sourceHash,
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Hosted model summarised accepted source blocks.',
                ]],
            ], 200),
            'https://api.sarvam.ai/*' => Http::response(['message' => 'must not call Sarvam'], 500),
        ]);

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")
            ->assertOk()
            ->assertJsonPath('document.title', 'Hosted Block Synopsis')
            ->assertJsonPath('document.latest_generation.provider', 'hosted_model')
            ->assertJsonPath('document.latest_generation.source_sha256', $sourceHash);

        Http::assertSent(fn ($request) => $request->url() === 'http://hosted-model.test/v1/synopsis'
            && $request->hasHeader('X-Vani-Setu-Source-Sha256', $sourceHash)
            && $request['source']['label'] === 'Approved Chief consolidation'
            && $request['source']['sha256'] === $sourceHash
            && $request['source']['text'] === null);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sarvam.ai'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated')->latest('id')->firstOrFail();
        $this->assertSame($sourceHash, $audit->payload['source_sha256']);
        $this->assertSame('hosted_model', $audit->payload['generation']['provider']);
    }

    public function test_block_based_generation_requires_matching_source_notes_label(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();
        $sourceHash = $this->consolidationSourceHash($consolidation);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => fn ($request) => Http::response([
                'title' => 'Hosted Block Synopsis With Wrong Source',
                'body' => "Hosted Block Synopsis With Wrong Source\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Wrong block source label should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Accepted source block.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'request_id' => $request['request_id'],
                'source_sha256' => $sourceHash,
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Wrong block source label should force fallback.',
                ]],
            ], 200),
        ]);

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")
            ->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_source_notes_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Block Synopsis With Wrong Source', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_source_notes_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertSame($sourceHash, $audit->payload['source_sha256']);
    }

    public function test_hosted_model_invalid_template_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Invalid Hosted Draft',
                'body' => 'This response is missing the governed synopsis template sections.',
            ], 200),
            'https://api.sarvam.ai/*' => Http::response(['message' => 'must not call Sarvam'], 500),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => "Shri R. Patil (Karnataka): Raised delays in National Highway 75 expansion and requested a time-bound statement from the Ministry.\n\nThe Minister: Stated that revised milestones will be shared with Members after verification.",
        ])->assertOk();

        $this->assertStringContainsString('Source Notes', (string) $response->json('document.body'));
        $this->assertStringNotContainsString('Invalid Hosted Draft', (string) $response->json('document.body'));
        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_missing_section', $response->json('document.latest_generation.fallback_reason'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sarvam.ai'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('hosted_model_missing_section', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_embedded_section_names_without_headings_fall_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted drafts must use governed section headings.\n\nThe Minister: Section names embedded in prose must not satisfy the template contract.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Prose Only Hosted Draft',
                'body' => "Prose Only Hosted Draft\n\nThis paragraph mentions Source Notes before Synopsis and Attribution Notes before Editorial Status, but none of them are actual governed headings.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Embedded section names should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_missing_section', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Prose Only Hosted Draft', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_missing_section', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_scrambled_template_sections_fall_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted drafts must keep the governed section order.\n\nThe Minister: Scrambled sections should not be accepted as a complete synopsis.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Scrambled Hosted Draft',
                'body' => "Scrambled Hosted Draft\n\nSource Notes\nSource: Writer pasted proceedings text\n\nAttribution Notes\n- Proceedings: source excerpt - Scrambled sections.\n\nSynopsis\n- Proceedings: Scrambled sections should force fallback.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Scrambled sections should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_section_order', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Scrambled Hosted Draft', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_invalid_section_order', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_body_title_mismatch_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted draft title metadata must match the body heading.\n\nThe Minister: Mismatched titles should not be persisted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Canonical Hosted Draft Title',
                'body' => "Different Hosted Draft Title\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Title mismatch should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Title mismatch.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Title mismatch should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_title_body_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Different Hosted Draft Title', (string) $response->json('document.body'));
        $this->assertNotSame('Canonical Hosted Draft Title', $response->json('document.title'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_title_body_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_source_notes_must_match_requested_source_label(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted drafts must visibly identify the requested source label.\n\nThe Minister: A correct source hash with the wrong Source Notes label should not be accepted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Wrong Source Label',
                'body' => "Hosted Draft With Wrong Source Label\n\nSource Notes\nSource: Approved Chief consolidation\n\nSynopsis\n- Proceedings: Wrong visible source labels should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Wrong source label.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Wrong visible source labels should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_source_notes_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Wrong Source Label', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_source_notes_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_invalid_editorial_status_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted drafts must preserve the governed editorial status.\n\nThe Minister: A model-specific status line should not be accepted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Bad Editorial Status',
                'body' => "Hosted Draft With Bad Editorial Status\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Bad editorial status should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Editorial status.\n\nEditorial Status: Ready for publication without writer review.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Bad editorial status should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_editorial_status', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Bad Editorial Status', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_invalid_editorial_status', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_missing_title_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted drafts must include their own title.\n\nThe Minister: Blank titles should not be silently replaced by fallback metadata.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => '   ',
                'body' => "Untitled Hosted Draft\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Missing title should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Missing title.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Missing title should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_missing_title', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Untitled Hosted Draft', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_missing_title', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_oversize_title_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted titles must remain within writer workspace limits.\n\nThe Minister: Oversize model titles should not be persisted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => str_repeat('T', 256),
                'body' => "Oversize Title Hosted Draft\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Oversize title should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Hosted titles.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Oversize title should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_title_too_long', $response->json('document.latest_generation.fallback_reason'));
        $this->assertNotSame(str_repeat('T', 256), $response->json('document.title'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_title_too_long', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_non_string_title_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted titles must be text.\n\nThe Minister: Array titles must not be coerced into saved draft titles.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => ['Array Title'],
                'body' => "Typed Title Hosted Draft\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Typed title should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Hosted titles.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Typed title should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_title', $response->json('document.latest_generation.fallback_reason'));
        $this->assertNotSame('Array', $response->json('document.title'));
        $this->assertStringNotContainsString('Typed Title Hosted Draft', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_invalid_title', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_oversize_body_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted bodies must remain within writer workspace limits.\n\nThe Minister: Oversize model bodies should not be persisted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Oversize Body Hosted Draft',
                'body' => "Oversize Body Hosted Draft\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Oversize body should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Hosted bodies.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.\n".str_repeat('A', 50001),
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Oversize body should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_body_too_long', $response->json('document.latest_generation.fallback_reason'));
        $this->assertLessThanOrEqual(50000, mb_strlen((string) $response->json('document.body')));
        $this->assertStringNotContainsString(str_repeat('A', 200), (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_body_too_long', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_missing_body_field_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted drafts must use the governed body field.\n\nThe Minister: Alias fields should not bypass the response contract.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Body Alias',
                'synopsis' => "Hosted Draft With Body Alias\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Body aliases should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Body alias.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Body aliases should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_missing_body', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Body Alias', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_missing_body', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_non_string_body_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted body must be text.\n\nThe Minister: Array bodies must not be coerced into saved draft content.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Typed Body Hosted Draft',
                'body' => ['not text'],
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Typed body should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_body', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Typed Body Hosted Draft', (string) $response->json('document.body'));
        $this->assertStringNotContainsString('not text', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_invalid_body', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_missing_structured_attributions_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Shri R. Patil (Karnataka): Raised delays in National Highway 75 expansion and requested a time-bound statement from the Ministry.\n\nThe Minister: Stated that revised milestones will be shared with Members after verification.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Body Without Attributions',
                'body' => "Hosted Body Without Attributions\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Body is valid but structured attributions are missing.\n\nAttribution Notes\n- Proceedings: source excerpt - Missing attribution rows.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_missing_attributions', $response->json('document.latest_generation.fallback_reason'));
        $this->assertNotEmpty($response->json('document.latest_generation.request_id'));
        $this->assertStringNotContainsString('Hosted Body Without Attributions', (string) $response->json('document.body'));
        $this->assertNotEmpty($response->json('document.attributions'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('fallback', $audit->payload['generation']['provider']);
        $this->assertSame('hosted_model_missing_attributions', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
        $this->assertSame($response->json('document.latest_generation.request_id'), $audit->payload['generation']['request_id']);
    }

    public function test_hosted_model_attribution_notes_must_match_structured_rows(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted attribution notes must agree with structured attribution rows.\n\nThe Minister: A body that names a different speaker should not be accepted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Mismatched Attribution Notes',
                'body' => "Hosted Draft With Mismatched Attribution Notes\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Mismatched attribution notes should force fallback.\n\nAttribution Notes\n- Different Member: source excerpt - This does not match the structured row.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Mismatched attribution notes should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_attribution_notes_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Mismatched Attribution Notes', (string) $response->json('document.body'));
        $this->assertNotSame('Different Member', $response->json('document.attributions.0.speaker_name'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_attribution_notes_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_extra_attribution_note_without_structured_row_falls_back(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted attribution notes must not contain extra speakers.\n\nThe Minister: Every note label must have a structured row.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Extra Attribution Note',
                'body' => "Hosted Draft With Extra Attribution Note\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Extra attribution notes should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Matching structured row.\n- Extra Member: source excerpt - No structured attribution row exists.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Extra attribution notes should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_attribution_notes_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Extra Attribution Note', (string) $response->json('document.body'));
        $this->assertNotSame('Extra Member', $response->json('document.attributions.0.speaker_name'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_attribution_notes_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_constituency_qualified_labels_must_match_exactly(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Shri R. Patil (Karnataka): Hosted attribution labels must include constituency when structured rows do.\n\nThe Minister: Dropping constituency from visible labels should not be accepted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => fn ($request) => Http::response([
                'title' => 'Hosted Draft With Incomplete Label',
                'body' => "Hosted Draft With Incomplete Label\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Shri R. Patil: Missing constituency should force fallback.\n\nAttribution Notes\n- Shri R. Patil: source excerpt - Missing constituency.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'request_id' => $request['request_id'],
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Shri R. Patil',
                    'constituency' => 'Karnataka',
                    'summary_text' => 'Missing constituency should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_attribution_notes_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Incomplete Label', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_attribution_notes_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_synopsis_bullets_must_match_structured_rows(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted synopsis bullets must agree with structured attribution rows.\n\nThe Minister: A synopsis bullet naming a different speaker should not be accepted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Mismatched Synopsis Bullet',
                'body' => "Hosted Draft With Mismatched Synopsis Bullet\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Different Member: This visible synopsis bullet does not match the structured row.\n\nAttribution Notes\n- Proceedings: source excerpt - Matching structured row.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Mismatched synopsis bullets should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_synopsis_notes_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Mismatched Synopsis Bullet', (string) $response->json('document.body'));
        $this->assertNotSame('Different Member', $response->json('document.attributions.0.speaker_name'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_synopsis_notes_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_synopsis_prose_without_bullets_falls_back(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted synopsis content must use governed bullet labels.\n\nThe Minister: Mentioning the speaker in prose should not satisfy structured row alignment.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Prose Synopsis',
                'body' => "Hosted Draft With Prose Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\nProceedings is mentioned here, but this is prose rather than a governed bullet label.\n\nAttribution Notes\n- Proceedings: source excerpt - Matching structured row.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Prose-only synopsis should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_synopsis_notes_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Prose Synopsis', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_synopsis_notes_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_synopsis_bullet_without_text_falls_back(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted synopsis bullets must include governed visible text.\n\nThe Minister: A label-only bullet should not be accepted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Empty Synopsis Bullet',
                'body' => "Hosted Draft With Empty Synopsis Bullet\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings:\n\nAttribution Notes\n- Proceedings: source excerpt - Matching structured row.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Label-only synopsis bullets should force fallback.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_synopsis_notes_mismatch', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Empty Synopsis Bullet', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_synopsis_notes_mismatch', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_too_many_attributions_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted attribution rows must remain bounded for the writer workspace.\n\nThe Minister: Excessive attribution rows should not be persisted.";
        $attributions = collect(range(1, 101))->map(fn (int $index): array => [
            'speaker_name' => "Speaker {$index}",
            'constituency' => null,
            'summary_text' => "Summary {$index}",
        ])->all();
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Too Many Attributions',
                'body' => "Hosted Draft With Too Many Attributions\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Too many attribution rows should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Attribution rows.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => $attributions,
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_too_many_attributions', $response->json('document.latest_generation.fallback_reason'));
        $this->assertNotCount(101, $response->json('document.attributions'));
        $this->assertStringNotContainsString('Hosted Draft With Too Many Attributions', (string) $response->json('document.body'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_too_many_attributions', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_object_attributions_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted attributions must be a list of rows.\n\nThe Minister: Object-shaped attribution maps should not be persisted.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Object Attributions',
                'body' => "Hosted Draft With Object Attributions\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Object-shaped attribution rows should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Object attribution.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [
                    'speaker_one' => [
                        'speaker_name' => 'Proceedings',
                        'constituency' => null,
                        'summary_text' => 'Object-shaped attribution should force fallback.',
                    ],
                ],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_attributions_shape', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Object Attributions', (string) $response->json('document.body'));
        $this->assertNotEmpty($response->json('document.attributions'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_invalid_attributions_shape', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_malformed_attribution_row_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted attribution rows must all be complete objects.\n\nThe Minister: Mixed malformed rows should not be silently dropped.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Malformed Attribution',
                'body' => "Hosted Draft With Malformed Attribution\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Malformed rows should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Malformed row.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [
                    [
                        'speaker_name' => 'Proceedings',
                        'constituency' => null,
                        'summary_text' => 'Valid row should not hide malformed rows.',
                    ],
                    [
                        'speaker_name' => 'The Minister',
                        'constituency' => null,
                        'summary_text' => '   ',
                    ],
                ],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_attribution_row', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Malformed Attribution', (string) $response->json('document.body'));
        $this->assertNotSame('Valid row should not hide malformed rows.', $response->json('document.attributions.0.summary_text'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_invalid_attribution_row', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_non_string_attribution_fields_fall_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted attribution fields must be real strings.\n\nThe Minister: Array values must not be coerced into synopsis text.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Typed Attribution Failure',
                'body' => "Hosted Draft With Typed Attribution Failure\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Typed attribution failures should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Typed attribution failure.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => ['Proceedings'],
                    'constituency' => null,
                    'summary_text' => 'Array speaker names must not be coerced.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_invalid_attribution_row', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Typed Attribution Failure', (string) $response->json('document.body'));
        $this->assertNotSame('Array', $response->json('document.attributions.0.speaker_name'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_invalid_attribution_row', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_hosted_model_oversize_attribution_field_falls_back_with_audit_reason(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: Hosted attribution fields must remain within writer workspace limits.\n\nThe Minister: Oversize attribution summaries should not be truncated into the draft.";
        $oversizeSummary = str_repeat('S', 2001);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => Http::response([
                'title' => 'Hosted Draft With Oversize Attribution',
                'body' => "Hosted Draft With Oversize Attribution\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Oversize attribution fields should force fallback.\n\nAttribution Notes\n- Proceedings: source excerpt - Attribution summary.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'source_sha256' => hash('sha256', $sourceText),
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => $oversizeSummary,
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $response = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->assertSame('fallback', $response->json('document.latest_generation.provider'));
        $this->assertSame('hosted_model_attribution_too_long', $response->json('document.latest_generation.fallback_reason'));
        $this->assertStringNotContainsString('Hosted Draft With Oversize Attribution', (string) $response->json('document.body'));
        $this->assertNotSame($oversizeSummary, $response->json('document.attributions.0.summary_text'));

        $audit = AuditLog::query()->where('action', 'synopsis.draft.generated_from_text')->latest('id')->firstOrFail();
        $this->assertSame('hosted_model_attribution_too_long', $audit->payload['generation']['fallback_reason']);
        $this->assertSame(200, $audit->payload['generation']['http_status']);
    }

    public function test_history_returns_edits_with_audit_context(): void
    {
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();
        $sourceText = "Shri R. Patil (Karnataka): Raised delays in National Highway 75 expansion and requested a time-bound statement from the Ministry.\n\nThe Minister: Stated that revised milestones will be shared with Members after verification.";

        $generate = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'title' => 'History Synopsis',
            'body' => $generate->json('document.body')."\n\nWriter reviewed this generated draft.",
            'version' => $generate->json('document.version'),
            'attributions' => $generate->json('document.attributions'),
        ])->assertOk();

        $this->getJson("/api/synopsis/chunks/{$consolidation->id}/history")
            ->assertOk()
            ->assertJsonCount(2, 'edits')
            ->assertJsonPath('edits.0.kind', 'save')
            ->assertJsonPath('edits.1.kind', 'generate')
            ->assertJsonPath('edits.0.audit_log.action', 'synopsis.draft.save')
            ->assertJsonPath('edits.1.audit_log.action', 'synopsis.draft.generated_from_text')
            ->assertJsonPath('edits.1.audit_evidence.source_sha256', hash('sha256', $sourceText))
            ->assertJsonPath('edits.1.audit_evidence.generation_provider', 'fallback')
            ->assertJsonPath('edits.1.audit_evidence.generation_model', 'vani-setu-synopsis')
            ->assertJsonPath('edits.1.audit_evidence.generation_fallback_reason', 'endpoint_not_configured')
            ->assertJsonPath('audit_events.1.audit_evidence.source_sha256', hash('sha256', $sourceText))
            ->assertJsonPath('audit_events.1.audit_evidence.generation_provider', 'fallback')
            ->assertJsonMissingPath('edits.1.audit_log.payload');
    }

    public function test_history_returns_hosted_generation_request_evidence(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();
        $sourceText = "Proceedings: Hosted history evidence should expose request correlation.\n\nThe Minister: Writers need compact request ids and HTTP status without raw audit payloads.";
        $sourceHash = hash('sha256', $sourceText);
        $requestId = null;
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => function ($request) use ($sourceHash, &$requestId) {
                $requestId = $request['request_id'];

                return Http::response([
                    'title' => 'Hosted History Evidence Synopsis',
                    'body' => "Hosted History Evidence Synopsis\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Hosted history evidence was generated.\n\nAttribution Notes\n- Proceedings: source excerpt - Request evidence.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                    'request_id' => $requestId,
                    'source_sha256' => $sourceHash,
                    'attributions' => [[
                        'speaker_name' => 'Proceedings',
                        'constituency' => null,
                        'summary_text' => 'Hosted history evidence was generated.',
                    ]],
                ], 200);
            },
        ]);

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])->assertOk();

        $this->getJson("/api/synopsis/chunks/{$consolidation->id}/history")
            ->assertOk()
            ->assertJsonPath('edits.0.audit_evidence.generation_provider', 'hosted_model')
            ->assertJsonPath('edits.0.audit_evidence.generation_http_status', 200)
            ->assertJsonPath('edits.0.audit_evidence.generation_request_id', $requestId)
            ->assertJsonPath('audit_events.0.audit_evidence.generation_provider', 'hosted_model')
            ->assertJsonPath('audit_events.0.audit_evidence.generation_http_status', 200)
            ->assertJsonPath('audit_events.0.audit_evidence.generation_request_id', $requestId)
            ->assertJsonMissingPath('edits.0.audit_log.payload');
    }

    public function test_history_returns_hosted_fallback_detail_without_raw_payload(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();
        $sourceText = "Proceedings: Hosted fallback diagnostics should be compactly visible.\n\nThe Minister: Writers should not need raw audit payloads to see model gateway failures.";
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => fn () => throw new \RuntimeException('model gateway timeout after 20 seconds'),
        ]);

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])
            ->assertOk()
            ->assertJsonPath('document.latest_generation.provider', 'fallback')
            ->assertJsonPath('document.latest_generation.fallback_reason', 'hosted_model_exception')
            ->assertJsonPath('document.latest_generation.fallback_detail', 'model gateway timeout after 20 seconds');

        $this->getJson("/api/synopsis/chunks/{$consolidation->id}/history")
            ->assertOk()
            ->assertJsonPath('edits.0.audit_evidence.generation_fallback_reason', 'hosted_model_exception')
            ->assertJsonPath('edits.0.audit_evidence.generation_fallback_detail', 'model gateway timeout after 20 seconds')
            ->assertJsonPath('audit_events.0.audit_evidence.generation_fallback_detail', 'model gateway timeout after 20 seconds')
            ->assertJsonMissingPath('edits.0.audit_log.payload');
    }

    public function test_draft_save_requires_current_version(): void
    {
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $generate = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")->assertOk();

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'body' => $generate->json('document.body')."\n\nMissing version should not overwrite.",
            'attributions' => $generate->json('document.attributions'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('version');

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'body' => $generate->json('document.body')."\n\nStale version should not overwrite.",
            'version' => $generate->json('document.version') - 1,
            'attributions' => $generate->json('document.attributions'),
        ])
            ->assertStatus(409)
            ->assertJsonPath('current_version', $generate->json('document.version'))
            ->assertJsonPath('current_title', $generate->json('document.title'))
            ->assertJsonPath('current_body', $generate->json('document.body'))
            ->assertJsonPath('current_attributions.0.speaker_name', $generate->json('document.attributions.0.speaker_name'))
            ->assertJsonPath('current_attributions.0.summary_text', $generate->json('document.attributions.0.summary_text'));
    }

    public function test_draft_save_trims_and_requires_meaningful_body(): void
    {
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $generate = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")->assertOk();

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'title' => 'Whitespace Body Synopsis',
            'body' => " \n\t ",
            'version' => $generate->json('document.version'),
            'attributions' => $generate->json('document.attributions'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $saved = $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'title' => 'Trimmed Body Synopsis',
            'body' => " \nProceedings: Writer saved a meaningful trimmed synopsis body.\n ",
            'version' => $generate->json('document.version'),
            'attributions' => $generate->json('document.attributions'),
        ])
            ->assertOk()
            ->assertJsonPath('document.body', 'Proceedings: Writer saved a meaningful trimmed synopsis body.');

        $this->assertSame($generate->json('document.version') + 1, $saved->json('document.version'));
    }

    public function test_draft_save_requires_attributions_to_be_list_rows(): void
    {
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $generate = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")->assertOk();

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'title' => 'Keyed Attribution Synopsis',
            'body' => $generate->json('document.body')."\n\nWriter attempted a keyed attribution payload.",
            'version' => $generate->json('document.version'),
            'attributions' => [
                'speaker_one' => [
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Keyed attribution payload should fail validation.',
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attributions');

        $document = SynopsisDocument::query()
            ->where('consolidation_id', $consolidation->id)
            ->firstOrFail();

        $this->assertSame($generate->json('document.version'), $document->version);
        $this->assertStringNotContainsString('Writer attempted a keyed attribution payload.', $document->body);
    }

    public function test_draft_save_requires_complete_bounded_attribution_rows(): void
    {
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $generate = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")->assertOk();

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'title' => 'Incomplete Attribution Synopsis',
            'body' => $generate->json('document.body')."\n\nWriter attempted an incomplete attribution payload.",
            'version' => $generate->json('document.version'),
            'attributions' => [[
                'speaker_name' => 'Proceedings',
                'constituency' => null,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attributions.0.summary_text');

        $tooManyAttributions = collect(range(1, 101))->map(fn (int $index): array => [
            'speaker_name' => "Speaker {$index}",
            'constituency' => null,
            'summary_text' => "Summary {$index}",
        ])->all();

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'title' => 'Excess Attribution Synopsis',
            'body' => $generate->json('document.body')."\n\nWriter attempted too many attribution rows.",
            'version' => $generate->json('document.version'),
            'attributions' => $tooManyAttributions,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attributions');

        $document = SynopsisDocument::query()
            ->where('consolidation_id', $consolidation->id)
            ->firstOrFail();

        $this->assertSame($generate->json('document.version'), $document->version);
        $this->assertStringNotContainsString('Writer attempted an incomplete attribution payload.', $document->body);
        $this->assertStringNotContainsString('Writer attempted too many attribution rows.', $document->body);
    }

    public function test_draft_save_trims_and_requires_meaningful_attribution_text(): void
    {
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $generate = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")->assertOk();

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'title' => '   ',
            'body' => $generate->json('document.body')."\n\nWriter attempted whitespace attribution fields.",
            'version' => $generate->json('document.version'),
            'attributions' => [[
                'speaker_name' => '   ',
                'constituency' => '  ',
                'summary_text' => '   ',
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'attributions.0.speaker_name',
                'attributions.0.summary_text',
            ]);

        $saved = $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'title' => '   Trimmed Attribution Synopsis   ',
            'body' => $generate->json('document.body')."\n\nWriter saved trimmed attribution fields.",
            'version' => $generate->json('document.version'),
            'attributions' => [[
                'speaker_name' => '  Proceedings  ',
                'constituency' => '   ',
                'summary_text' => '  Trimmed attribution summary.  ',
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('document.title', 'Trimmed Attribution Synopsis')
            ->assertJsonPath('document.attributions.0.speaker_name', 'Proceedings')
            ->assertJsonPath('document.attributions.0.constituency', null)
            ->assertJsonPath('document.attributions.0.summary_text', 'Trimmed attribution summary.');

        $this->assertSame($generate->json('document.version') + 1, $saved->json('document.version'));
    }

    public function test_submitted_synopsis_cannot_be_mutated_back_to_draft(): void
    {
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $generate = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")->assertOk();
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/submit")
            ->assertOk()
            ->assertJsonPath('document.status', 'submitted');

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'body' => $generate->json('document.body')."\n\nLate submitted edit.",
            'version' => $generate->json('document.version'),
            'attributions' => $generate->json('document.attributions'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Submitted synopsis documents cannot be edited.');

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Submitted synopsis documents cannot be edited.');

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/author")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Submitted synopsis documents cannot be edited.');

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/submit")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only draft synopsis documents can be submitted.');

        $this->assertSame('submitted', SynopsisDocument::query()
            ->where('consolidation_id', $consolidation->id)
            ->value('status'));
    }

    public function test_authoring_from_scratch_clears_prior_generation_evidence(): void
    {
        config()->set('services.synopsis.model_url', 'http://hosted-model.test/v1/synopsis');
        $sourceText = "Proceedings: The writer first asks the hosted model for a draft.\n\nThe Minister: The writer then discards that draft and authors from scratch.";
        $sourceHash = hash('sha256', $sourceText);
        Http::fake([
            'http://hosted-model.test/v1/synopsis' => fn ($request) => Http::response([
                'title' => 'Hosted Draft To Discard',
                'body' => "Hosted Draft To Discard\n\nSource Notes\nSource: Writer pasted proceedings text\n\nSynopsis\n- Proceedings: Hosted draft that will be discarded.\n\nAttribution Notes\n- Proceedings: source excerpt - writer first asks.\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
                'request_id' => $request['request_id'],
                'source_sha256' => $sourceHash,
                'attributions' => [[
                    'speaker_name' => 'Proceedings',
                    'constituency' => null,
                    'summary_text' => 'Hosted draft that will be discarded.',
                ]],
            ], 200),
        ]);
        Sanctum::actingAs($this->writer());
        $consolidation = $this->acceptedConsolidation();

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate-from-text", [
            'source_text' => $sourceText,
        ])
            ->assertOk()
            ->assertJsonPath('document.latest_generation.provider', 'hosted_model')
            ->assertJsonPath('document.latest_generation.source_sha256', $sourceHash);

        $author = $this->postJson("/api/synopsis/chunks/{$consolidation->id}/author")
            ->assertOk()
            ->assertJsonPath('document.source_mode', 'scratch')
            ->assertJsonPath('document.ai_first_draft', false)
            ->assertJsonPath('document.latest_generation', null);

        $save = $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'title' => 'Scratch Synopsis',
            'body' => 'Proceedings: Writer authored this synopsis independently after discarding the hosted draft.',
            'version' => $author->json('document.version'),
            'attributions' => [],
        ])
            ->assertOk()
            ->assertJsonPath('document.latest_generation', null);

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/submit")
            ->assertOk()
            ->assertJsonPath('document.latest_generation', null);
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/finalise")
            ->assertOk()
            ->assertJsonPath('document.latest_generation', null);

        $submitAudit = AuditLog::query()->where('action', 'synopsis.draft.submit')->latest('id')->firstOrFail();
        $this->assertNull($submitAudit->payload['source_sha256']);
        $this->assertNull($submitAudit->payload['generation']);

        $finaliseAudit = AuditLog::query()->where('action', 'synopsis.finalise')->latest('id')->firstOrFail();
        $this->assertNull($finaliseAudit->payload['source_sha256']);
        $this->assertNull($finaliseAudit->payload['generation']);

        $this->getJson('/api/synopsis/queue')
            ->assertOk()
            ->assertJsonPath('0.latest_generation', null)
            ->assertJsonPath('0.source_mode', 'scratch')
            ->assertJsonPath('0.ai_first_draft', false);
        $this->assertSame('Scratch Synopsis', $save->json('document.title'));
    }

    public function test_final_document_cannot_be_edited_and_audit_chain_verifies(): void
    {
        $writer = $this->writer();
        $consolidation = $this->acceptedConsolidation();
        Sanctum::actingAs($writer);

        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/author")->assertOk();
        $version = SynopsisDocument::query()->where('consolidation_id', $consolidation->id)->firstOrFail()->version;
        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'body' => 'Proceedings: Writer authored this synopsis from scratch.',
            'version' => $version,
            'attributions' => [],
        ])->assertOk();
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/submit")->assertOk();
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/finalise")->assertOk();

        $this->putJson("/api/synopsis/chunks/{$consolidation->id}/draft", [
            'body' => 'Late edit after final.',
            'version' => SynopsisDocument::query()->where('consolidation_id', $consolidation->id)->firstOrFail()->version,
        ])->assertStatus(422);
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/generate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Final synopsis documents cannot be edited.');
        $this->postJson("/api/synopsis/chunks/{$consolidation->id}/author")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Final synopsis documents cannot be edited.');

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
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

    private function consolidationSourceHash(ChiefConsolidation $consolidation): string
    {
        $payload = $consolidation->blocks()
            ->orderBy('sequence')
            ->get()
            ->map(fn ($block): array => [
                'id' => $block->id,
                'sequence' => $block->sequence,
                'version' => $block->version,
                'member_id' => $block->member_id,
                'custom_member_id' => $block->custom_member_id,
                'text_sha256' => hash('sha256', (string) $block->text),
            ])->values()->all();

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function mutateFirstSourceBlock(ChiefConsolidation $consolidation, string $text): void
    {
        $block = $consolidation->blocks()->orderBy('sequence')->firstOrFail();
        $block->forceFill([
            'text' => $text,
            'version' => $block->version + 1,
        ])->save();
    }
}
