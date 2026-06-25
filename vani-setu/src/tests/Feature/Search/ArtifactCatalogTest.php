<?php

namespace Tests\Feature\Search;

use App\Modules\Core\Models\User;
use App\Modules\Search\Models\StoredArtifact;
use App\Modules\Search\Services\ArtifactCatalogService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArtifactCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $artifactRoot = storage_path("framework/testing/disks/vani_artifacts_".spl_object_id($this));
        File::deleteDirectory($artifactRoot);
        File::ensureDirectoryExists($artifactRoot);

        config([
            "filesystems.disks.vani_artifacts" => [
                "driver" => "local",
                "root" => $artifactRoot,
                "throw" => false,
            ],
        ]);
        Storage::forgetDisk("vani_artifacts");
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/indexes/artifacts/search')) {
                return Http::response([
                    'hits' => [[
                        'id' => '1',
                        'title' => 'budget-notes.txt',
                        '_formatted' => ['search_text' => 'Budget <mark>allocation</mark> notes'],
                    ]],
                    'page' => 1,
                    'totalPages' => 1,
                    'totalHits' => 1,
                ]);
            }

            return Http::response(['taskUid' => 1], 202);
        });

        $this->seed(DatabaseSeeder::class);
    }

    public function test_hygiene_indexes_non_sensitive_artifacts(): void
    {
        Storage::disk('vani_artifacts')->put('ingest/budget-notes.txt', 'Budget allocation notes for the committee.');

        app(ArtifactCatalogService::class)->registerStoredObject([
            'disk' => 'vani_artifacts',
            'path' => 'ingest/budget-notes.txt',
            'mime_type' => 'text/plain',
            'title' => 'budget-notes.txt',
            'source_module' => 'committee',
            'tags' => ['budget', 'committee'],
        ]);

        $this->artisan('artifacts:hygiene --limit=50')
            ->expectsOutputToContain('Processed 1 artifacts.')
            ->assertExitCode(0);

        $artifact = StoredArtifact::query()->firstOrFail();
        $this->assertSame('indexed', $artifact->search_status);
        $this->assertNotNull($artifact->indexed_at);
        $this->assertStringContainsString('Budget allocation notes', (string) $artifact->search_text);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/indexes/artifacts/documents'));
    }

    public function test_artifact_search_route_returns_hits_and_audits(): void
    {
        $user = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/search/artifacts?q=allocation&filters='.urlencode(json_encode(['media_family' => 'text'])))
            ->assertOk()
            ->assertJsonPath('hits.0._formatted.search_text', 'Budget <mark>allocation</mark> notes');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'search.artifacts.query',
            'subject_type' => null,
            'subject_id' => null,
        ]);
    }

    public function test_catalog_requires_core_metadata_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Artifact attribute [source_module] is required.');

        app(ArtifactCatalogService::class)->registerStoredObject([
            'disk' => 'vani_artifacts',
            'path' => 'ingest/missing.txt',
            'mime_type' => 'text/plain',
            'title' => 'missing.txt',
        ]);
    }

    public function test_artifact_audit_detects_orphans(): void
    {
        Storage::disk('vani_artifacts')->put('orphaned/file.txt', 'orphan');

        $this->artisan('artifacts:audit --fail-on-orphans')
            ->expectsOutputToContain('vani_artifacts:orphaned/file.txt')
            ->expectsOutputToContain('Orphaned files detected:')
            ->assertExitCode(1);
    }
}
