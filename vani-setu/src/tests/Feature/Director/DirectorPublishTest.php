<?php

namespace Tests\Feature\Director;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use App\Modules\Director\Models\DirectorPublishJob;
use App\Modules\Js\Models\JsWindow;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DirectorPublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('DIRECTOR_CRC_DISK=director_crc');
        $_ENV['DIRECTOR_CRC_DISK'] = 'director_crc';
        Storage::fake('director_crc');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_director_publish_job_lifecycle(): void
    {
        $director = User::query()->where('employee_id', 'DIR-001')->firstOrFail();
        $window = $this->window('approved');
        Sanctum::actingAs($director);

        $inbox = $this->getJson('/api/director/inbox')
            ->assertOk()
            ->assertJsonFragment(['window_id' => $window->id, 'status' => 'queued']);

        $jobId = $inbox->json('0.id');

        $this->postJson("/api/director/jobs/{$jobId}/publish")
            ->assertOk();

        $job = DirectorPublishJob::query()->findOrFail($jobId);
        $this->assertSame('published', $job->status);
        $this->assertNotNull($job->ran_at);
        $this->assertNotNull($job->finished_at);
        $this->assertNotNull($job->sansad_url);
        $this->assertNotNull($job->crc_pdf_path);
        Storage::disk('director_crc')->assertExists($job->crc_pdf_path);

        $this->getJson("/api/director/jobs/{$jobId}/log")
            ->assertOk()
            ->assertJsonFragment(['action' => 'director.crc.generated'])
            ->assertJsonFragment(['action' => 'director.sansad.pushed']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'director.job.queued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'director.crc.generated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'director.sansad.pushed']);
    }

    public function test_publish_requires_approved_window(): void
    {
        $director = User::query()->where('employee_id', 'DIR-001')->firstOrFail();
        $window = $this->window('open');
        $job = DirectorPublishJob::query()->create([
            'window_id' => $window->id,
            'director_user_id' => $director->id,
            'queued_at' => now(),
            'status' => 'queued',
        ]);
        Sanctum::actingAs($director);

        $this->postJson("/api/director/jobs/{$job->id}/publish")
            ->assertStatus(422);
    }

    public function test_director_jobs_index_lists_publish_jobs(): void
    {
        $director = User::query()->where('employee_id', 'DIR-001')->firstOrFail();
        $window = $this->window('approved');
        $job = DirectorPublishJob::query()->create([
            'window_id' => $window->id,
            'director_user_id' => $director->id,
            'queued_at' => now(),
            'status' => 'queued',
        ]);
        Sanctum::actingAs($director);

        $this->getJson('/api/director/jobs')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $job->id,
                'window_id' => $window->id,
                'status' => 'queued',
            ]);
    }

    public function test_director_publish_leaves_audit_chain_clean(): void
    {
        $director = User::query()->where('employee_id', 'DIR-001')->firstOrFail();
        $window = $this->window('approved');
        Sanctum::actingAs($director);

        $jobId = $this->getJson('/api/director/inbox')->json('0.id');
        $this->postJson("/api/director/jobs/{$jobId}/publish")->assertOk();

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    private function window(string $status): JsWindow
    {
        $sitting = Sitting::query()->create([
            'session_no' => 2026,
            'sitting_no' => random_int(1, 9999),
            'sitting_date' => '2026-05-19',
            'status' => 'live',
        ]);
        $slot = Slot::query()->create([
            'sitting_id' => $sitting->id,
            'code' => 'P1',
            'start_offset_ms' => 0,
            'duration_ms' => 300000,
            'topic' => 'Test',
            'status' => 'committed_full',
        ]);
        Block::query()->create([
            'slot_id' => $slot->id,
            'sequence' => 1,
            'start_ms' => 0,
            'end_ms' => 1000,
            'original_lang' => 'en',
            'chief_lang' => 'en',
            'ai_action' => 'native',
            'ai_text' => 'approved text',
            'text' => 'approved text',
            'version' => 1,
            'reporter_edit_count' => 0,
        ]);

        return JsWindow::query()->create([
            'sitting_id' => $sitting->id,
            'window_code' => '1200-1300',
            'starts_at_offset_ms' => 0,
            'duration_ms' => 3600000,
            'status' => $status,
        ]);
    }
}
