<?php

namespace Tests\Feature;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditHash;
use App\Modules\Core\Services\Audit\AuditLogger;
use Database\Seeders\AuditGenesisSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\AssertsAuditChains;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use AssertsAuditChains;
    use RefreshDatabase;

    public function test_genesis_can_be_seeded_once(): void
    {
        $this->seed(AuditGenesisSeeder::class);
        $this->seed(AuditGenesisSeeder::class);

        $this->assertSame(1, AuditLog::query()->toBase()->where('action', 'system.audit.init')->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system.audit.init',
            'actor_id' => null,
            'actor_role' => 'system',
            'chain_segment' => 'on_record',
        ]);
    }

    public function test_logger_chains_hashes_correctly(): void
    {
        $user = User::factory()->create();
        app()->instance('audit.actor_id', $user->id);
        app()->instance('audit.actor_role', 'reporter');
        app()->instance('request.ip', '127.0.0.1');
        app()->instance('request.ua', 'AuditLogTest');
        app()->instance('request.id', '00000000-0000-0000-0000-000000000001');

        $chainSegment = 'speech_to_text';
        $beforeId = AuditLog::query()->toBase()->max('id') ?? 0;
        $previousHash = AuditLog::query()
            ->toBase()
            ->where('chain_segment', $chainSegment)
            ->latest('id')
            ->value('this_hash');

        $logger = app(AuditLogger::class);
        $logger->log('capture.record', $user, ['b' => 2, 'a' => 1]);
        $logger->log('capture.edit', $user, ['nested' => ['z' => 1, 'a' => 2]]);
        $logger->log('capture.commit', $user, ['items' => [['b' => 2, 'a' => 1]]]);

        $rows = AuditLog::query()
            ->toBase()
            ->where('id', '>', $beforeId)
            ->where('chain_segment', $chainSegment)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $rows);

        foreach ($rows as $row) {
            $this->assertSame($previousHash, $row->prev_hash);

            $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
            $expected = hash('sha256', AuditHash::preImage(
                $row->prev_hash,
                $row->actor_id,
                $row->actor_role,
                $row->action,
                $row->subject_type,
                $row->subject_id,
                $payload,
                $row->created_at,
                $row->chain_segment,
            ));

            $this->assertSame($expected, $row->this_hash);
            $previousHash = $row->this_hash;
        }
    }

    public function test_update_throws(): void
    {
        $this->seed(AuditGenesisSeeder::class);

        $auditLog = AuditLog::query()->firstOrFail();
        $auditLog->action = 'system.audit.changed';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AuditLog is append-only. Use AuditLogger::log().');

        $auditLog->save();
    }

    public function test_delete_throws(): void
    {
        $this->seed(AuditGenesisSeeder::class);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AuditLog is append-only. Use AuditLogger::log().');

        $auditLog->delete();
    }

    public function test_db_blocks_raw_update(): void
    {
        $this->seed(AuditGenesisSeeder::class);
        $auditLogId = AuditLog::query()->value('id');

        if (DB::getDriverName() !== 'pgsql') {
            DB::table('audit_logs')->where('id', $auditLogId)->update([
                'action' => 'system.audit.changed',
            ]);

            $this->assertDatabaseHas('audit_logs', [
                'id' => $auditLogId,
                'action' => 'system.audit.changed',
            ]);

            return;
        }

        $this->expectException(\Throwable::class);

        DB::table('audit_logs')->where('id', $auditLogId)->update([
            'action' => 'system.audit.changed',
        ]);
    }

    public function test_verify_command_passes_on_clean_chain(): void
    {
        $this->seed(AuditGenesisSeeder::class);
        app(AuditLogger::class)->log('system.audit.check', null, ['status' => 'ok']);

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    public function test_logger_assigns_named_chain_segments_and_verify_reports_them(): void
    {
        $logger = app(AuditLogger::class);

        $logger->log('reporter.slot.audit_sweep', null, ['slot_id' => 10]);
        $logger->log('translator.assignment.commit', null, ['assignment_id' => 20]);
        $logger->log('committee.workflow.forward', null, ['committee_sitting_id' => 30]);
        $logger->log('in_camera.flag.applied', null, ['document_id' => 40]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reporter.slot.audit_sweep',
            'chain_segment' => 'reporter',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'translator.assignment.commit',
            'chain_segment' => 'translator',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'committee.workflow.forward',
            'chain_segment' => 'committee',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'in_camera.flag.applied',
            'chain_segment' => 'committee.in_camera',
        ]);

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Segment committee intact')
            ->expectsOutputToContain('Segment committee.in_camera intact')
            ->expectsOutputToContain('Segment reporter intact')
            ->expectsOutputToContain('Segment translator intact')
            ->expectsOutputToContain('Chain intact')
            ->assertExitCode(0);
    }

    public function test_segment_prev_hashes_are_isolated_from_other_segments(): void
    {
        $logger = app(AuditLogger::class);

        $reporterOne = $logger->log('reporter.slot.audit_sweep', null, ['slot_id' => 1]);
        $translator = $logger->log('translator.assignment.commit', null, ['assignment_id' => 1]);
        $reporterTwo = $logger->log('reporter.slot.duration_finalised', null, ['slot_id' => 1]);
        $committee = $logger->log('committee.workflow.forward', null, ['committee_sitting_id' => 1]);
        $inCameraOne = $logger->log('in_camera.flag.applied', null, ['document_id' => 1]);
        $inCameraTwo = $logger->log('in_camera.block.viewed', null, ['document_id' => 1]);

        $this->assertSame($reporterOne->this_hash, $reporterTwo->prev_hash);
        $this->assertNotSame($translator->this_hash, $reporterTwo->prev_hash);
        $this->assertNull($committee->prev_hash);
        $this->assertNull($inCameraOne->prev_hash);
        $this->assertSame($inCameraOne->this_hash, $inCameraTwo->prev_hash);
    }

    public function test_verify_fails_when_segment_membership_is_tampered(): void
    {
        $logger = app(AuditLogger::class);

        $logger->log('reporter.slot.audit_sweep', null, ['slot_id' => 1]);
        $tampered = $logger->log('translator.assignment.commit', null, ['assignment_id' => 1]);

        $this->tamperAuditLog($tampered->id, [
            'chain_segment' => 'reporter',
        ]);

        $exitCode = Artisan::call('audit:verify');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("Mismatch at row {$tampered->id} in segment reporter", Artisan::output());
    }

    public function test_verify_command_fails_on_tampered_payload(): void
    {
        $this->seed(AuditGenesisSeeder::class);
        $auditLogId = AuditLog::query()->value('id');

        $payload = json_encode(['tampered' => true], JSON_THROW_ON_ERROR);
        $this->tamperAuditLog($auditLogId, [
            'payload' => DB::getDriverName() === 'pgsql'
                ? DB::raw("'".$payload."'::jsonb")
                : $payload,
        ]);

        $exitCode = Artisan::call('audit:verify');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("Mismatch at row {$auditLogId}", Artisan::output());
    }
}
