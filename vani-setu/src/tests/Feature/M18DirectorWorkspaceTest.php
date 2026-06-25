<?php

namespace Tests\Feature;

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
use Tests\Concerns\AssertsAuditChains;
use Tests\TestCase;

class M18DirectorWorkspaceTest extends TestCase
{
    use AssertsAuditChains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('DIRECTOR_CRC_DISK=director_crc');
        $_ENV['DIRECTOR_CRC_DISK'] = 'director_crc';
        Storage::fake('director_crc');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_director_workspace_routes_render_shell(): void
    {
        $job = $this->publishJob('approved');

        $this->get('/app/director')
            ->assertOk()
            ->assertSee('data-workspace="director"', false)
            ->assertSee('M18 Director Workspace');

        $this->get("/app/director/jobs/{$job->id}")
            ->assertOk()
            ->assertSee('data-initial-job="'.$job->id.'"', false);
    }

    public function test_director_login_and_inbox_loads(): void
    {
        $window = $this->window('approved');

        $login = $this->postJson('/api/auth/login', [
            'employee_id' => 'DIR-001',
            'password' => 'director123',
        ])->assertOk()
            ->assertJsonPath('roles.0', 'director');

        $this->withToken($login->json('token'))
            ->getJson('/api/director/inbox')
            ->assertOk()
            ->assertJsonFragment(['window_id' => $window->id, 'status' => 'queued']);
    }

    public function test_non_director_cannot_access_director_inbox(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'SG-001')->firstOrFail());

        $this->getJson('/api/director/inbox')->assertForbidden();
    }

    public function test_director_detail_publish_and_audit_log(): void
    {
        $job = $this->publishJob('approved');
        Sanctum::actingAs($this->director());

        $this->getJson("/api/director/jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('job.id', $job->id)
            ->assertJsonPath('job.window.status', 'approved');

        $this->postJson("/api/director/jobs/{$job->id}/publish")
            ->assertOk()
            ->assertJsonPath('job.status', 'published');

        $job->refresh();
        $this->assertNotNull($job->crc_pdf_path);
        $this->assertNotNull($job->sansad_url);
        Storage::disk('director_crc')->assertExists($job->crc_pdf_path);

        $this->getJson("/api/director/jobs/{$job->id}/log")
            ->assertOk()
            ->assertJsonFragment(['action' => 'director.crc.generated'])
            ->assertJsonFragment(['action' => 'director.sansad.pushed']);

        $this->assertAuditActionsChained('on_record', [
            'director.crc.generated',
            'director.sansad.pushed',
        ]);
    }

    public function test_published_job_is_read_only_for_republish(): void
    {
        $job = $this->publishJob('approved');
        $job->forceFill(['status' => 'published', 'finished_at' => now()])->save();
        Sanctum::actingAs($this->director());

        $this->postJson("/api/director/jobs/{$job->id}/publish")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Publish job has already started.');
    }

    private function director(): User
    {
        return User::query()->where('employee_id', 'DIR-001')->firstOrFail();
    }

    private function publishJob(string $windowStatus): DirectorPublishJob
    {
        return DirectorPublishJob::query()->create([
            'window_id' => $this->window($windowStatus)->id,
            'director_user_id' => $this->director()->id,
            'queued_at' => now(),
            'status' => 'queued',
        ]);
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
            'topic' => 'Director test',
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
