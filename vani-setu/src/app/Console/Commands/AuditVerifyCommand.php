<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Services\Audit\AuditHash;
use Illuminate\Console\Command;

class AuditVerifyCommand extends Command
{
    protected $signature = 'audit:verify {--segment=* : Verify only the given chain segment(s)}';

    protected $description = 'Verify the audit log hash chain by chain segment.';

    public function handle(): int
    {
        $segments = $this->option('segment');
        $segments = $segments === [] ? $this->segments() : $segments;
        $total = 0;
        $firstGenesisAt = null;

        foreach ($segments as $segment) {
            $result = $this->verifySegment($segment);

            if ($result === false) {
                return self::FAILURE;
            }

            [$count, $genesisAt] = $result;
            $total += $count;
            $firstGenesisAt ??= $genesisAt;
            $this->info("Segment {$segment} intact · {$count} rows · genesis at {$genesisAt}");
        }

        $this->info("Chain intact · {$total} rows · genesis at {$firstGenesisAt}");

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
     * @return array{int, mixed}|false
     */
    private function verifySegment(string $segment): array|false
    {
        $previousHash = null;
        $count = 0;
        $genesisAt = null;

        foreach (AuditLog::query()->toBase()->where('chain_segment', $segment)->orderBy('id')->cursor() as $row) {
            $payload = is_string($row->payload) ? json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR) : (array) $row->payload;
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

            if ($row->prev_hash !== $previousHash || ($expected !== $row->this_hash && $legacyExpected !== $row->this_hash)) {
                $this->error("Mismatch at row {$row->id} in segment {$segment}");
                $this->line("expected prev_hash: ".($previousHash ?? 'NULL'));
                $this->line("stored prev_hash: ".($row->prev_hash ?? 'NULL'));
                $this->line("expected hash: {$expected}");
                $this->line("stored hash: {$row->this_hash}");

                return false;
            }

            $previousHash = $row->this_hash;
            $genesisAt ??= $row->created_at;
            $count++;
        }

        return [$count, $genesisAt];
    }
}
