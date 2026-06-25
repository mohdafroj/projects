<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Services\Audit\AuditHash;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditRepairCommand extends Command
{
    protected $signature = 'audit:repair {--segment=* : Repair only the given chain segment(s)} {--force : Apply updates to the database}';

    protected $description = 'Recompute audit log prev_hash and this_hash values in insertion order for maintenance recovery.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('Dry run only. Re-run with --force to apply repairs.');
        }

        $segments = $this->option('segment');
        $segments = $segments === [] ? $this->segments() : $segments;

        $totalRows = 0;
        $totalRepairs = 0;

        foreach ($segments as $segment) {
            [$rows, $repairs] = $this->repairSegment($segment, (bool) $this->option('force'));
            $totalRows += $rows;
            $totalRepairs += $repairs;
            $this->info("Segment {$segment}: scanned {$rows} row(s), ".($this->option('force') ? "repaired {$repairs}" : "would repair {$repairs}"));
        }

        $this->info("Audit repair complete: scanned {$totalRows} row(s), ".($this->option('force') ? "repaired {$totalRepairs}" : "would repair {$totalRepairs}"));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function segments(): array
    {
        return AuditLog::query()
            ->toBase()
            ->select('chain_segment')
            ->distinct()
            ->orderBy('chain_segment')
            ->pluck('chain_segment')
            ->all();
    }

    /**
     * @return array{int, int}
     */
    private function repairSegment(string $segment, bool $apply): array
    {
        $rows = AuditLog::query()
            ->toBase()
            ->where('chain_segment', $segment)
            ->orderBy('id')
            ->get();

        $repairs = [];
        $previousHash = null;

        foreach ($rows as $row) {
            $payload = is_string($row->payload)
                ? json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR)
                : (array) $row->payload;

            $expectedHash = hash('sha256', AuditHash::preImage(
                $previousHash,
                $row->actor_id,
                $row->actor_role,
                $row->action,
                $row->subject_type,
                $row->subject_id,
                $payload ?? [],
                $row->created_at,
                $row->chain_segment,
            ));

            if ($row->prev_hash !== $previousHash || $row->this_hash !== $expectedHash) {
                $repairs[] = [
                    'id' => $row->id,
                    'prev_hash' => $previousHash,
                    'this_hash' => $expectedHash,
                ];
            }

            $previousHash = $expectedHash;
        }

        if ($apply && $repairs !== []) {
            DB::transaction(function () use ($repairs): void {
                DB::statement('SET LOCAL session_replication_role = replica');
                foreach ($repairs as $repair) {
                    DB::table('audit_logs')
                        ->where('id', $repair['id'])
                        ->update([
                            'prev_hash' => $repair['prev_hash'],
                            'this_hash' => $repair['this_hash'],
                        ]);
                }
            });
        }

        return [$rows->count(), count($repairs)];
    }
}
