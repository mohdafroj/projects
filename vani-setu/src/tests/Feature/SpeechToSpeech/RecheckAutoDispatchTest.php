<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Core\Models\User;
use App\Modules\SpeechToSpeech\Jobs\RecheckSessionJob;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecheckAutoDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_finish_dispatches_recheck_session_job_when_auto_dispatch_enabled(): void
    {
        config(['services.s2s_recheck.auto_dispatch' => true]);
        Queue::fake();

        $session = $this->makeSession();
        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $this->postJson("/api/s2s/sessions/{$session->id}/finish")->assertOk();

        Queue::assertPushed(RecheckSessionJob::class, function (RecheckSessionJob $job) use ($session) {
            return $job->sessionId === $session->id;
        });
    }

    public function test_finish_does_not_dispatch_recheck_when_auto_dispatch_disabled(): void
    {
        config(['services.s2s_recheck.auto_dispatch' => false]);
        Queue::fake();

        $session = $this->makeSession();
        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $this->postJson("/api/s2s/sessions/{$session->id}/finish")->assertOk();

        Queue::assertNotPushed(RecheckSessionJob::class);
    }

    public function test_public_finish_dispatches_recheck_session_job_when_auto_dispatch_enabled(): void
    {
        config(['services.s2s_recheck.auto_dispatch' => true]);
        Queue::fake();

        $session = $this->makeSession();

        $this->postJson("/speech-to-speech/sessions/{$session->id}/finish")
            ->assertOk()
            ->assertJsonPath('session.status', 'finished');

        Queue::assertPushed(RecheckSessionJob::class, function (RecheckSessionJob $job) use ($session) {
            return $job->sessionId === $session->id;
        });
    }

    public function test_public_finish_does_not_dispatch_recheck_when_auto_dispatch_disabled(): void
    {
        config(['services.s2s_recheck.auto_dispatch' => false]);
        Queue::fake();

        $session = $this->makeSession();

        $this->postJson("/speech-to-speech/sessions/{$session->id}/finish")
            ->assertOk()
            ->assertJsonPath('session.status', 'finished');

        Queue::assertNotPushed(RecheckSessionJob::class);
    }

    private function makeSession(): S2sSession
    {
        return S2sSession::query()->create([
            'title' => 'Recheck Auto Dispatch',
            'mode' => 'upload',
            'input_source' => 'uploaded_file',
            'listener_scope' => 'outside_house',
            'source_lang' => 'gu-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }
}
