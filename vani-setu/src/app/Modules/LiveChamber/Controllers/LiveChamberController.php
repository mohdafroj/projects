<?php

namespace App\Modules\LiveChamber\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LiveChamberController extends Controller
{
    public function snapshot(Request $request, AuditLogger $audit): array
    {
        $withLiveRelations = ['slots' => fn ($query) => $query->with(['blocks.member', 'blocks.customMember', 'assignments.assignee:id,name,employee_id'])->orderBy('start_offset_ms')];

        $sitting = Sitting::query()
            ->where('status', 'live')
            ->whereHas('slots.assignments', fn ($query) => $query->where(fn ($q) => $q
                ->where('assignee_user_id', $request->user()->id)
                ->orWhere('user_id', $request->user()->id)))
            ->with($withLiveRelations)
            ->orderByRaw('started_at is null')
            ->latest('started_at')
            ->latest('created_at')
            ->latest('sitting_date')
            ->latest('id')
            ->first();

        $sitting ??= Sitting::query()
            ->where('status', 'live')
            ->with($withLiveRelations)
            ->orderByRaw('started_at is null')
            ->latest('started_at')
            ->latest('created_at')
            ->latest('sitting_date')
            ->latest('id')
            ->first();

        $slot = $sitting ? $this->currentSlot($sitting) : null;
        $blocks = $slot?->blocks ?? collect();
        $speakerBlock = $this->speakerBlock($blocks);
        $confidence = $slot ? $this->latestAsrConfidence($slot) : null;

        $audit->log('live_chamber.snapshot.viewed', $slot, [
            'user_id' => $request->user()->id,
            'sitting_id' => $sitting?->id,
            'slot_id' => $slot?->id,
            'capture_status' => $slot?->status ?? $sitting?->status ?? 'offline',
            'asr_confidence' => $confidence,
        ]);

        return [
            'generated_at' => now()->toISOString(),
            'sitting' => $sitting?->only(['id', 'session_no', 'sitting_no', 'sitting_date', 'status', 'started_at', 'ended_at']),
            'current_slot' => $slot ? [
                ...$slot->only(['id', 'sitting_id', 'code', 'start_offset_ms', 'duration_ms', 'topic', 'status']),
                'workflow_stage' => $slot->overallWorkflowStage(),
                'assignments' => $slot->assignments->map(fn ($assignment) => [
                    ...$assignment->only(['id', 'lang_role', 'status', 'workflow_stage', 'last_workflow_action_at']),
                    'assignee' => $assignment->assignee?->only(['id', 'name', 'employee_id']),
                ])->values(),
            ] : null,
            'speaker' => $speakerBlock ? $this->speaker($speakerBlock) : null,
            'capture' => [
                'status' => $slot?->status ?? $sitting?->status ?? 'offline',
                'blocks_total' => $blocks->count(),
                'blocks_with_text' => $blocks->filter(fn (Block $block) => trim((string) $block->text) !== '' || trim((string) $block->ai_text) !== '')->count(),
                'assignments_open' => $slot?->assignments->whereIn('status', ['open', 'in_progress'])->count() ?? 0,
            ],
            'asr' => [
                'confidence' => $confidence,
                'quality' => $this->confidenceQuality($confidence),
            ],
            'recent_blocks' => $blocks->sortByDesc('start_ms')->take(5)->map(fn (Block $block) => [
                'id' => $block->id,
                'sequence' => $block->sequence,
                'start_ms' => $block->start_ms,
                'end_ms' => $block->end_ms,
                'text' => $block->text,
                'ai_text' => $block->ai_text,
                'speaker' => $this->speaker($block),
            ])->values(),
        ];
    }

    private function currentSlot(Sitting $sitting): ?Slot
    {
        $slots = $sitting->slots;
        if ($sitting->started_at) {
            $elapsedMs = max(0, $sitting->started_at->diffInMilliseconds(now(), false));
            $byClock = $slots->first(fn (Slot $slot) => $slot->start_offset_ms <= $elapsedMs && $slot->start_offset_ms + $slot->duration_ms > $elapsedMs);
            if ($byClock) {
                return $byClock;
            }
        }

        return $slots->firstWhere('status', 'in_progress')
            ?? $slots->firstWhere('status', 'open')
            ?? $slots->first();
    }

    /**
     * @param  Collection<int, Block>  $blocks
     */
    private function speakerBlock(Collection $blocks): ?Block
    {
        return $blocks
            ->filter(fn (Block $block) => $block->member_id || $block->custom_member_id)
            ->sortByDesc('start_ms')
            ->first();
    }

    private function speaker(Block $block): ?array
    {
        $speaker = $block->member ?: $block->customMember;
        if (! $speaker) {
            return null;
        }

        return [
            'id' => $speaker->id,
            'kind' => $block->member ? 'member' : 'custom',
            'name_en' => $speaker->name_en,
            'name_hi' => $speaker->name_hi,
            'party' => $speaker->party ?? null,
            'state_jur' => $speaker->state_jur ?? null,
            'role_title' => $speaker->role_title ?? null,
        ];
    }

    private function latestAsrConfidence(Slot $slot): ?float
    {
        $blockIds = $slot->blocks->pluck('id')->map(fn ($id) => (string) $id)->all();
        if ($blockIds === []) {
            return null;
        }

        $log = AuditLog::query()
            ->where('action', 'asr.block.ingested')
            ->where('subject_type', (new Block())->getMorphClass())
            ->whereIn('subject_id', $blockIds)
            ->latest('id')
            ->first();

        $confidence = $log?->payload['confidence'] ?? null;

        return is_numeric($confidence) ? round((float) $confidence, 3) : null;
    }

    private function confidenceQuality(?float $confidence): string
    {
        if ($confidence === null) {
            return 'unknown';
        }

        return match (true) {
            $confidence >= 0.85 => 'high',
            $confidence >= 0.65 => 'medium',
            default => 'low',
        };
    }
}
