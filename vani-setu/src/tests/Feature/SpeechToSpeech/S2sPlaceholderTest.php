<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\Core\Models\User;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Laravel\Sanctum\Sanctum;
use Tests\ModuleTestCase;

class S2sPlaceholderTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedModuleBase();
    }

    public function test_s2s_routes_are_live_instead_of_placeholders(): void
    {
        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $session = $this->postJson('/api/s2s/sessions', [
            'title' => 'Route Smoke',
            'mode' => 'live',
            'input_source' => 'microphone',
            'listener_scope' => 'hybrid',
            'source_lang' => 'auto',
            'target_langs' => ['hi-IN'],
        ])->assertOk()
            ->assertJsonPath('title', 'Route Smoke')
            ->assertJsonPath('available_target_langs.0', 'hi-IN')
            ->json();

        $this->getJson("/api/s2s/sessions/{$session['id']}")
            ->assertOk()
            ->assertJsonPath('id', $session['id']);

        $this->getJson("/api/s2s/sessions/{$session['id']}/segments")
            ->assertOk()
            ->assertJsonPath('session_id', $session['id']);
    }

    public function test_schema_migrations_applied_and_session_finish_is_audited(): void
    {
        $this->assertDatabaseCount('s2s_sessions', 0);
        $this->assertDatabaseCount('s2s_segments', 0);

        $session = S2sSession::query()->create([
            'title' => 'Finish Smoke',
            'mode' => 'upload',
            'input_source' => 'uploaded_file',
            'listener_scope' => 'outside_house',
            'source_lang' => 'en-IN',
            'target_lang' => 'hi-IN',
            'available_target_langs' => ['hi-IN'],
            'status' => 'processing',
            'started_at' => now(),
        ]);

        Sanctum::actingAs(User::query()->where('employee_id', 'RPT-001')->firstOrFail());

        $this->postJson("/api/s2s/sessions/{$session->id}/finish")
            ->assertOk()
            ->assertJsonPath('status', 'finished');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 's2s.session.finished',
            'chain_segment' => 'speech_to_speech',
        ]);
    }
}
