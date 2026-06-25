<?php

namespace Tests\Feature;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaptureFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_reporter_sees_only_their_assignments(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        Sanctum::actingAs($reporter);

        $response = $this->getJson('/api/me/assignments')->assertOk();

        $slotCodes = collect($response->json())->pluck('slot.code')->all();
        $this->assertContains('1A', $slotCodes);
        $this->assertContains('1F', $slotCodes);
        $this->assertNotContains('2A', $slotCodes);
    }

    public function test_reporter_can_edit_block_in_their_lane(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $block = $this->block('1A', 'en');
        Sanctum::actingAs($reporter);

        $response = $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Updated English capture text.',
            'version' => $block->version,
        ])->assertOk();

        $response->assertJsonPath('version', 2);
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'text' => 'Updated English capture text.',
            'version' => 2,
        ]);
    }

    public function test_reporter_cannot_edit_block_in_another_lane(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $block = $this->block('1A', 'hi');
        Sanctum::actingAs($reporter);

        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Not allowed.',
            'version' => $block->version,
        ])->assertForbidden();
    }

    public function test_reporter_cannot_edit_after_commit(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        $block = $this->block('1A', 'en');
        Sanctum::actingAs($reporter);

        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'en'])->assertOk();

        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Locked text.',
            'version' => $block->version,
        ])->assertForbidden();
    }

    public function test_block_edit_writes_audit_log(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $block = $this->block('1A', 'en');
        Sanctum::actingAs($reporter);

        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Audit edit text.',
            'version' => $block->version,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'capture.block.edit',
            'subject_type' => Block::class,
            'subject_id' => (string) $block->id,
        ]);
    }

    public function test_optimistic_lock_conflict_returns_409(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $block = $this->block('1A', 'en');
        $block->forceFill(['version' => 2, 'text' => 'Current text'])->save();
        Sanctum::actingAs($reporter);

        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Stale edit.',
            'version' => 1,
        ])->assertStatus(409)
            ->assertJsonPath('current_version', 2)
            ->assertJsonPath('current_text', 'Current text');
    }

    public function test_speaker_set_writes_audit_log(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $block = $this->block('1A', 'en');
        $member = \App\Modules\Core\Models\Member::query()->where('roster_id', 'R034')->firstOrFail();
        Sanctum::actingAs($reporter);

        $this->putJson("/api/blocks/{$block->id}/speaker", [
            'member_id' => $member->id,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'capture.block.speaker']);
    }

    public function test_custom_member_create_writes_audit_log(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $block = $this->block('1A', 'en');
        Sanctum::actingAs($reporter);

        $this->postJson("/api/blocks/{$block->id}/custom-members", [
            'name_en' => 'SPECIAL INVITEE',
            'name_hi' => 'विशेष आमंत्रित',
            'role_title' => 'Invitee',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', ['action' => 'capture.block.custom_member']);
        $this->assertNotNull($block->fresh()->custom_member_id);
    }

    public function test_slot_commit_writes_audit_log_and_locks_lane(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Sanctum::actingAs($reporter);

        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'en'])->assertOk();

        $assignment = SlotAssignment::query()
            ->where('slot_id', $slot->id)
            ->where('lang_role', 'en')
            ->firstOrFail();

        $this->assertSame('committed', $assignment->status);
        $this->assertNotNull($assignment->committed_audit_log_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'capture.slot.commit']);
    }

    public function test_reporter_audio_chunk_upload_persists_sequence_file(): void
    {
        Storage::fake('vani_audio');
        putenv('REPORTER_AUDIO_MOCK=false');
        $_ENV['REPORTER_AUDIO_MOCK'] = 'false';

        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Sanctum::actingAs($reporter);

        $this->postJson("/api/reporter/slot/{$slot->id}/audio-chunk", [
            'seq' => 1,
            'chunk' => UploadedFile::fake()->create('chunk-1.webm', 4, 'audio/webm'),
        ])->assertOk()
            ->assertJsonPath('seq', 1)
            ->assertJsonPath('path', "reporter-audio/{$slot->id}/chunk-1.webm")
            ->assertJsonPath('uri', "s3://vani-audio-raw-rs/reporter-audio/{$slot->id}/chunk-1.webm")
            ->assertJsonPath('storage_provider', 'minio');

        Storage::disk('vani_audio')->assertExists("reporter-audio/{$slot->id}/chunk-1.webm");
        $this->assertDatabaseHas('audit_logs', ['action' => 'reporter.audio.chunk.uploaded']);
    }

    public function test_reporter_audio_close_assembles_minio_object_and_calls_tijori(): void
    {
        Storage::fake('vani_audio');
        Http::fake([
            'tijori-router.tijori-system.svc.cluster.local/*' => Http::response([
                'transcript' => 'Proceedings transcript.',
                'language' => 'en-IN',
                'backend' => 'sarvam-saaras-v3',
                'latency_ms' => 312,
                'model_revision' => 'saaras-v3',
            ], 200),
        ]);
        putenv('REPORTER_AUDIO_MOCK=false');
        $_ENV['REPORTER_AUDIO_MOCK'] = 'false';

        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Storage::disk('vani_audio')->put("reporter-audio/{$slot->id}/chunk-1.webm", 'one');
        Storage::disk('vani_audio')->put("reporter-audio/{$slot->id}/chunk-2.webm", 'two');
        Sanctum::actingAs($reporter);

        $this->postJson("/api/reporter/slot/{$slot->id}/audio-close")
            ->assertOk()
            ->assertJsonPath('chunk_count', 2)
            ->assertJsonPath('path', "reporter-audio/{$slot->id}/slot-{$slot->id}.webm")
            ->assertJsonPath('uri', "s3://vani-audio-raw-rs/reporter-audio/{$slot->id}/slot-{$slot->id}.webm")
            ->assertJsonPath('storage_provider', 'minio')
            ->assertJsonPath('asr.provider', 'tijori')
            ->assertJsonPath('asr.status', 200)
            ->assertJsonPath('asr.detail.backend', 'sarvam-saaras-v3');

        Storage::disk('vani_audio')->assertExists("reporter-audio/{$slot->id}/slot-{$slot->id}.webm");
        Http::assertSent(fn ($request) => $request->hasHeader('X-Pipeline-Class', 'proceedings')
            && $request->hasHeader('X-Slot-Id', (string) $slot->id)
            && str_contains($request->url(), '/v1/asr'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'reporter.audio.closed']);
    }


    public function test_reporter_audio_chunk_accepts_assigned_m4a_file_and_returns_metadata(): void
    {
        Storage::fake('vani_audio');
        putenv('REPORTER_AUDIO_MOCK=false');
        $_ENV['REPORTER_AUDIO_MOCK'] = 'false';

        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Sanctum::actingAs($reporter);

        $this->postJson("/api/reporter/slot/{$slot->id}/audio-chunk", [
            'seq' => 1,
            'chunk' => UploadedFile::fake()->create('assigned-floor-audio.m4a', 64, 'audio/mp4'),
        ])->assertOk()
            ->assertJsonPath('slot_id', $slot->id)
            ->assertJsonPath('seq', 1)
            ->assertJsonPath('path', "reporter-audio/{$slot->id}/chunk-1.m4a")
            ->assertJsonPath('mime_type', 'audio/mp4')
            ->assertJsonPath('original_name', 'assigned-floor-audio.m4a');

        Storage::disk('vani_audio')->assertExists("reporter-audio/{$slot->id}/chunk-1.m4a");
        $this->assertDatabaseHas('audit_logs', ['action' => 'reporter.audio.chunk.uploaded']);
    }

    public function test_reporter_audio_close_preserves_m4a_extension_and_sends_audio_mp4_to_asr(): void
    {
        Storage::fake('vani_audio');
        Http::fake([
            'tijori-router.tijori-system.svc.cluster.local/*' => Http::response([
                'transcript' => 'Assigned m4a transcript from Sarvam.',
                'language' => 'en-IN',
                'backend' => 'sarvam-saaras-v3',
            ], 200),
        ]);
        putenv('REPORTER_AUDIO_MOCK=false');
        $_ENV['REPORTER_AUDIO_MOCK'] = 'false';

        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        Storage::disk('vani_audio')->put("reporter-audio/{$slot->id}/chunk-1.m4a", 'm4a-one');
        Sanctum::actingAs($reporter);

        $this->postJson("/api/reporter/slot/{$slot->id}/audio-close")
            ->assertOk()
            ->assertJsonPath('chunk_count', 1)
            ->assertJsonPath('path', "reporter-audio/{$slot->id}/slot-{$slot->id}.m4a")
            ->assertJsonPath('uri', "s3://vani-audio-raw-rs/reporter-audio/{$slot->id}/slot-{$slot->id}.m4a")
            ->assertJsonPath('asr.transcript', 'Assigned m4a transcript from Sarvam.');

        Storage::disk('vani_audio')->assertExists("reporter-audio/{$slot->id}/slot-{$slot->id}.m4a");
        Http::assertSent(fn ($request) => ($request->data()['mime_type'] ?? null) === 'audio/mp4'
            && ($request->data()['metadata']['filename'] ?? null) === "slot-{$slot->id}.m4a");
        $this->assertDatabaseHas('stored_artifacts', [
            'source_module' => 'capture',
            'media_family' => 'audio',
            'storage_path' => "reporter-audio/{$slot->id}/slot-{$slot->id}.m4a",
            'mime_type' => 'audio/mp4',
        ]);
    }

    public function test_reporter_audio_close_creates_capture_block_from_asr_for_empty_slot(): void
    {
        Storage::fake('vani_audio');
        Http::fake([
            'tijori-router.tijori-system.svc.cluster.local/*' => Http::response([
                'stt' => [
                    'text' => 'Fresh reporter audio transcript.',
                    'language_code' => 'hi-IN',
                    'confidence' => 0.94,
                ],
            ], 200),
        ]);
        putenv('REPORTER_AUDIO_MOCK=false');
        $_ENV['REPORTER_AUDIO_MOCK'] = 'false';

        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        $sitting = Sitting::query()->firstOrFail();
        $slot = Slot::query()->create([
            'sitting_id' => $sitting->id,
            'code' => 'ASR-FRESH',
            'start_offset_ms' => 0,
            'duration_ms' => 180000,
            'topic' => 'Fresh ASR close',
            'status' => 'open',
        ]);
        SlotAssignment::query()->create([
            'slot_id' => $slot->id,
            'user_id' => $reporter->id,
            'lang_role' => 'hi',
            'status' => 'open',
            'workflow_stage' => 'reporter',
        ]);
        Storage::disk('vani_audio')->put("reporter-audio/{$slot->id}/chunk-1.webm", 'one');
        Sanctum::actingAs($reporter);

        $this->postJson("/api/reporter/slot/{$slot->id}/audio-close")
            ->assertOk()
            ->assertJsonPath('closed', true)
            ->assertJsonPath('asr.transcript', 'Fresh reporter audio transcript.')
            ->assertJsonPath('asr.language', 'hi-IN');

        $block = Block::query()->where('slot_id', $slot->id)->firstOrFail();
        $this->assertSame('Fresh reporter audio transcript.', $block->text);
        $this->assertSame('hi', $block->original_lang);
        $this->assertSame('hi', $block->chief_lang);
        $this->assertSame('in_progress', $slot->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reporter.audio.asr_block_created']);
        $this->assertDatabaseHas('stored_artifacts', [
            'source_module' => 'capture',
            'media_family' => 'audio',
            'subject_id' => $slot->id,
        ]);
    }

    public function test_slot_status_transitions_open_to_in_progress_to_committed_partial_to_committed_full(): void
    {
        $slot = Slot::query()->where('code', '1D')->firstOrFail();
        $enReporter = User::query()->where('employee_id', 'RPT-011')->firstOrFail();
        $hiReporter = User::query()->where('employee_id', 'RPT-012')->firstOrFail();
        $block = $this->block('1D', 'en');

        $this->assertSame('open', $slot->status);

        Sanctum::actingAs($enReporter);
        $this->putJson("/api/blocks/{$block->id}", [
            'text' => 'Status transition edit.',
            'version' => $block->version,
        ])->assertOk();
        $this->assertSame('in_progress', $slot->fresh()->status);

        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'en'])->assertOk();
        $this->assertSame('committed_partial', $slot->fresh()->status);

        Sanctum::actingAs($hiReporter);
        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'hi'])->assertOk();
        $this->assertSame('committed_full', $slot->fresh()->status);
    }

    public function test_audit_chain_intact_after_full_capture(): void
    {
        $slot = Slot::query()->where('code', '1D')->firstOrFail();
        $enReporter = User::query()->where('employee_id', 'RPT-011')->firstOrFail();
        $hiReporter = User::query()->where('employee_id', 'RPT-012')->firstOrFail();

        Sanctum::actingAs($enReporter);
        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'en'])->assertOk();

        Sanctum::actingAs($hiReporter);
        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'hi'])->assertOk();

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    private function block(string $slotCode, string $lang): Block
    {
        return Block::query()
            ->whereHas('slot', fn ($query) => $query->where('code', $slotCode))
            ->where('original_lang', $lang)
            ->orderBy('sequence')
            ->firstOrFail();
    }
}
