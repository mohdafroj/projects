<?php

namespace App\Modules\Reporter\Services;

use App\Modules\Core\Models\Slot;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Capture\Services\ReporterAudioStorage;
use Illuminate\Support\Facades\Storage;

class SlotRecoveryService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * @return array{slot_id:int, chunk_count:int, missing_sequences:array<int>, complete:bool, partial:bool}
     */
    public function inspect(Slot $slot): array
    {
        $audioStorage = app(ReporterAudioStorage::class);
        $disk = Storage::disk($audioStorage->diskName());
        $dir = "reporter-audio/{$slot->id}";
        $sequences = collect($disk->files($dir))
            ->map(fn (string $path) => preg_match('/chunk-(\d+)\.webm$/', $path, $matches) ? (int) $matches[1] : null)
            ->filter()
            ->sort()
            ->values();

        $expected = $sequences->isEmpty() ? collect() : collect(range(1, (int) $sequences->max()));
        $missing = $expected->diff($sequences)->values()->all();

        return [
            'slot_id' => $slot->id,
            'chunk_count' => $sequences->count(),
            'missing_sequences' => $missing,
            'complete' => $sequences->isNotEmpty() && $missing === [],
            'partial' => $sequences->isNotEmpty() && $missing !== [],
        ];
    }

    /**
     * @return array{slot_id:int, chunk_count:int, missing_sequences:array<int>, complete:bool, partial:bool}
     */
    public function recordRecoveryState(Slot $slot, string $reason): array
    {
        $state = $this->inspect($slot);

        $this->audit->log('reporter.slot.recovery.inspected', $slot, [
            ...$state,
            'reason' => $reason,
        ]);

        return $state;
    }
}
