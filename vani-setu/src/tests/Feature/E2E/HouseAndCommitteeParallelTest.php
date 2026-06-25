<?php

namespace Tests\Feature\E2E;

use App\Modules\CommitteeSittings\Models\CommitteeSitting;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseAndCommitteeParallelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.asr.ingest_secret', 'local-asr-secret');
        Http::fake(['*' => Http::response(['taskUid' => 1, 'hits' => []])]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_house_and_committee_traces_share_one_intact_audit_chain(): void
    {
        $slot = Slot::query()->where('code', '1A')->firstOrFail();
        $block = Block::query()->where('slot_id', $slot->id)->orderBy('sequence')->firstOrFail();

        Sanctum::actingAs($this->user('RPT-001'));
        $this->postJson("/api/slots/{$slot->id}/commit", ['lang_role' => 'en'])
            ->assertOk();

        Sanctum::actingAs($this->user('COM-SEC-001'));
        $committeeSittingId = $this->postJson('/api/committee/sittings', [
            'committee_code' => 'DRPSC-IT',
            'committee_name' => 'Department-related Parliamentary Standing Committee on Information Technology',
            'committee_type' => 'DRPSC',
            'meeting_no' => '2',
            'scheduled_at' => '2026-06-02T10:00:00+05:30',
            'in_camera_default' => true,
            'witnesses' => [],
            'observers' => [],
        ])->assertCreated()->json('sitting.id');

        $committeeSitting = CommitteeSitting::query()->findOrFail($committeeSittingId);
        $this->postJson("/api/committee/sittings/{$committeeSitting->id}/capture-slots", [
            'slot_id' => $slot->id,
            'block_id' => $block->id,
            'text' => 'Committee ASR and search segregation block.',
            'in_camera' => true,
        ])->assertOk();

        $payload = json_encode([
            'slot_id' => $slot->id,
            'start_ms' => $block->start_ms,
            'end_ms' => $block->end_ms,
            'text' => 'ASR text routed to committee block.',
            'lang' => 'en',
        ], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = 'house-committee-parallel-'.bin2hex(random_bytes(8));
        $signature = hash_hmac('sha256', "{$timestamp}.{$nonce}.{$payload}", 'local-asr-secret');

        $this->call('POST', '/api/asr/ingest', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE_NONCE' => $nonce,
        ], $payload)->assertOk();

        Sanctum::actingAs($this->user('COM-OBS-001'));
        $this->getJson("/api/in-camera/blocks/{$block->id}")
            ->assertForbidden();

        $this->assertTrue(AuditLog::query()->where('action', 'capture.slot.commit')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'committee.capture.slot.commit')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'asr.block.ingested')->exists());

        $this->artisan('audit:verify')->expectsOutputToContain('Chain intact')->assertExitCode(0);
    }

    private function user(string $employeeId): User
    {
        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
