<?php

namespace Tests\Feature\Search;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private bool $returnSearchHits = false;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(function ($request) {
            if ($this->returnSearchHits && str_contains($request->url(), '/indexes/blocks/search')) {
                return Http::response([
                    'hits' => [[
                        'id' => '1',
                        'slot_id' => 1,
                        'text' => 'Budget discussion',
                        '_formatted' => ['text' => '<mark>Budget</mark> discussion'],
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

    public function test_reindex_pushes_blocks_to_meilisearch(): void
    {
        $this->artisan('search:reindex')
            ->expectsOutputToContain('Indexed')
            ->assertExitCode(0);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/indexes/blocks/documents'));
    }

    public function test_search_query_returns_hits_and_writes_audit_row(): void
    {
        $this->returnSearchHits = true;

        $user = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/search?q=budget&filters='.urlencode(json_encode(['original_lang' => 'en'])))
            ->assertOk()
            ->assertJsonPath('hits.0._formatted.text', '<mark>Budget</mark> discussion');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'search.query',
            'subject_type' => null,
            'subject_id' => null,
        ]);

        $this->assertSame(1, AuditLog::query()->where('action', 'search.query')->count());
    }
}
