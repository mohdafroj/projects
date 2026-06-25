<?php

namespace Tests\Concerns;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Services\Audit\AuditHash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait AssertsAuditChains
{
    /**
     * @param  list<string>  $actions
     */
    protected function assertAuditActionsChained(string $segment, array $actions): void
    {
        foreach ($actions as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'action' => $action,
                'chain_segment' => $segment,
            ]);
        }

        $this->assertAuditSegmentIntact($segment);

        $exitCode = Artisan::call('audit:verify', ['--segment' => [$segment]]);

        $this->assertSame(0, $exitCode, Artisan::output());
    }

    protected function assertAuditSegmentIntact(string $segment): void
    {
        $previousHash = null;
        $rows = AuditLog::query()
            ->toBase()
            ->where('chain_segment', $segment)
            ->orderBy('id')
            ->get();

        $this->assertNotCount(0, $rows, "No audit rows found for segment [{$segment}].");

        foreach ($rows as $row) {
            $payload = is_string($row->payload)
                ? json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR)
                : (array) $row->payload;
            $expected = hash('sha256', AuditHash::preImage(
                $row->prev_hash,
                $row->actor_id,
                $row->actor_role,
                $row->action,
                $row->subject_type,
                $row->subject_id,
                $payload ?? [],
                $row->created_at,
                $row->chain_segment,
            ));
            $legacyExpected = hash('sha256', AuditHash::preImage(
                $row->prev_hash,
                $row->actor_id,
                $row->actor_role,
                $row->action,
                $row->subject_type,
                $row->subject_id,
                $payload ?? [],
                $row->created_at,
                null,
            ));

            $this->assertSame($previousHash, $row->prev_hash, "Unexpected prev_hash at audit row {$row->id}.");
            $this->assertContains($row->this_hash, [$expected, $legacyExpected], "Unexpected this_hash at audit row {$row->id}.");

            $previousHash = $row->this_hash;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function tamperAuditLog(int $auditLogId, array $attributes): void
    {
        DB::transaction(function () use ($auditLogId, $attributes): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET LOCAL session_replication_role = replica');
            }

            DB::table('audit_logs')->where('id', $auditLogId)->update($attributes);
        });
    }
}
