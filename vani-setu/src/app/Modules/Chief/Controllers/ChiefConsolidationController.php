<?php

namespace App\Modules\Chief\Controllers;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Chief\Models\ChiefConsolidationCommit;
use App\Modules\Chief\Models\ChiefEdit;
use App\Modules\Chief\Models\ChiefSpeakerOverride;
use App\Modules\Chief\Requests\ChiefBlockUpdateRequest;
use App\Modules\Chief\Requests\ChiefCommitRequest;
use App\Modules\Chief\Requests\ChiefReturnRequest;
use App\Modules\Chief\Requests\ChiefSpeakerRequest;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\SlotWorkflowEvent;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChiefConsolidationController
{
    public function queue(Request $request)
    {
        $side = $this->langSide($request->user());

        return ChiefConsolidation::query()
            ->with(['sitting', 'commits'])
            ->orderBy('sitting_id')
            ->orderBy('window_code')
            ->get()
            ->filter(fn (ChiefConsolidation $consolidation) => $consolidation->inputsReady())
            ->filter(fn (ChiefConsolidation $consolidation) => ! $consolidation->commits->contains('lang_side', $side))
            ->values()
            ->map(fn (ChiefConsolidation $consolidation) => $this->summary($consolidation));
    }

    public function show(ChiefConsolidation $consolidation): array
    {
        return $this->detail($consolidation);
    }

    public function updateBlock(
        ChiefBlockUpdateRequest $request,
        ChiefConsolidation $consolidation,
        Block $block,
        AuditLogger $audit
    ) {
        return DB::transaction(function () use ($request, $consolidation, $block, $audit) {
            $side = $this->langSide($request->user());
            /** @var Block $locked */
            $locked = Block::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();

            if (! $this->blockInConsolidation($consolidation, $locked)) {
                abort(404);
            }

            if ($locked->chief_lang !== $side) {
                return response()->json(['message' => 'Block belongs to another chief lane.'], 403);
            }

            if ((int) $request->validated('version') !== $locked->version) {
                return response()->json([
                    'current_version' => $locked->version,
                    'current_text' => $locked->text,
                ], 409);
            }

            $before = $locked->text;
            $after = $request->validated('text');

            $locked->forceFill([
                'text' => $after,
                'version' => $locked->version + 1,
            ])->save();

            $auditLog = $audit->log('chief.block.edit', $locked, [
                'consolidation_id' => $consolidation->id,
                'lang_side' => $side,
                'before_excerpt' => mb_substr($before, 0, 120),
                'after_excerpt' => mb_substr($after, 0, 120),
            ]);

            ChiefEdit::query()->create([
                'consolidation_id' => $consolidation->id,
                'block_id' => $locked->id,
                'chief_user_id' => $request->user()->id,
                'kind' => 'text',
                'before' => $side === 'en' ? $before : null,
                'after' => $side === 'en' ? $after : null,
                'before_hi' => $side === 'hi' ? $before : null,
                'after_hi' => $side === 'hi' ? $after : null,
                'audit_log_id' => $auditLog->id,
            ]);

            return $locked->fresh(['member', 'customMember']);
        });
    }

    public function updateSpeaker(
        ChiefSpeakerRequest $request,
        ChiefConsolidation $consolidation,
        Block $block,
        AuditLogger $audit
    ) {
        return DB::transaction(function () use ($request, $consolidation, $block, $audit) {
            $side = $this->langSide($request->user());
            /** @var Block $locked */
            $locked = Block::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();

            if (! $this->blockInConsolidation($consolidation, $locked)) {
                abort(404);
            }

            if ($locked->chief_lang !== $side) {
                return response()->json(['message' => 'Block belongs to another chief lane.'], 403);
            }

            $before = $this->speakerSnapshot($locked);
            $memberId = $request->validated('member_id');
            $customMemberId = $request->validated('custom_member_id');

            $locked->forceFill([
                'member_id' => $memberId,
                'custom_member_id' => $customMemberId,
            ])->save();

            $after = $this->speakerSnapshot($locked->fresh(['member', 'customMember']));
            $auditLog = $audit->log('chief.block.speaker', $locked, [
                'consolidation_id' => $consolidation->id,
                'lang_side' => $side,
                'before' => $before,
                'after' => $after,
            ]);

            ChiefSpeakerOverride::query()->updateOrCreate(
                ['consolidation_id' => $consolidation->id, 'block_id' => $locked->id],
                [
                    'reporter_member_id' => $before['member_id'],
                    'chief_member_id' => $memberId,
                    'chief_custom_member_id' => $customMemberId,
                    'chief_user_id' => $request->user()->id,
                ],
            );

            ChiefEdit::query()->create([
                'consolidation_id' => $consolidation->id,
                'block_id' => $locked->id,
                'chief_user_id' => $request->user()->id,
                'kind' => 'speaker',
                'before' => $before['name_en'],
                'after' => $after['name_en'],
                'before_hi' => $before['name_hi'],
                'after_hi' => $after['name_hi'],
                'audit_log_id' => $auditLog->id,
            ]);

            return $locked->fresh(['member', 'customMember']);
        });
    }

    public function commit(ChiefCommitRequest $request, ChiefConsolidation $consolidation, AuditLogger $audit)
    {
        return DB::transaction(function () use ($request, $consolidation, $audit) {
            /** @var ChiefConsolidation $locked */
            $locked = ChiefConsolidation::query()->whereKey($consolidation->id)->lockForUpdate()->firstOrFail();
            $side = $request->validated('lang_side');

            if ($side !== $this->langSide($request->user())) {
                return response()->json(['message' => 'Cannot commit the other chief lane.'], 403);
            }

            if (! $locked->inputsReady()) {
                return response()->json(['message' => 'Reporter inputs are not ready for chief consolidation.'], 422);
            }

            $blocks = $locked->blocks()->where('chief_lang', $side);
            $auditLog = $audit->log('chief.consolidation.commit', $locked, [
                'lang_side' => $side,
                'block_count' => (clone $blocks)->count(),
                'edit_count' => $locked->edits()->whereHas('block', fn ($query) => $query->where('chief_lang', $side))->count(),
                'custom_member_count' => $locked->speakerOverrides()->whereNotNull('chief_custom_member_id')->count(),
            ]);

            ChiefConsolidationCommit::query()->updateOrCreate(
                ['consolidation_id' => $locked->id, 'lang_side' => $side],
                [
                    'chief_user_id' => $request->user()->id,
                    'block_count' => (clone $blocks)->count(),
                    'edit_count' => $locked->edits()->whereHas('block', fn ($query) => $query->where('chief_lang', $side))->count(),
                    'custom_member_count' => $locked->speakerOverrides()->whereNotNull('chief_custom_member_id')->count(),
                    'committed_at' => now(),
                    'committed_audit_log_id' => $auditLog->id,
                ],
            );

            $locked->forceFill(['status' => $this->nextCommitStatus($locked, $side)])->save();

            return $this->detail($locked->fresh());
        });
    }

    public function return(ChiefReturnRequest $request, ChiefConsolidation $consolidation, AuditLogger $audit)
    {
        return DB::transaction(function () use ($request, $consolidation, $audit) {
            $toStage = $request->validated('to_stage');
            $reason = $request->validated('reason');
            $auditLog = $audit->log('chief.consolidation.return', $consolidation, [
                'to_stage' => $toStage,
                'reason' => $reason,
            ]);

            SlotAssignment::query()
                ->whereIn('slot_id', $consolidation->slotIdsInWindow())
                ->where('workflow_stage', 'chief')
                ->each(function (SlotAssignment $assignment) use ($request, $toStage, $reason, $auditLog) {
                    $assignment->forceFill([
                        'workflow_stage' => $toStage,
                        'status' => $toStage === 'reporter' ? 'in_progress' : $assignment->status,
                        'committed_at' => $toStage === 'reporter' ? null : $assignment->committed_at,
                        'committed_audit_log_id' => $toStage === 'reporter' ? null : $assignment->committed_audit_log_id,
                        'last_workflow_action_at' => now(),
                    ])->save();

                    SlotWorkflowEvent::query()->create([
                        'slot_assignment_id' => $assignment->id,
                        'from_stage' => 'chief',
                        'to_stage' => $toStage,
                        'action' => 'return',
                        'actor_id' => $request->user()->id,
                        'actor_role' => $this->primaryRole($request->user()),
                        'reason' => $reason,
                        'audit_log_id' => $auditLog->id,
                        'created_at' => now(),
                    ]);
                });

            $consolidation->forceFill(['status' => 'open'])->save();

            return $this->detail($consolidation->fresh());
        });
    }

    public function history(ChiefConsolidation $consolidation): array
    {
        return [
            'edits' => $consolidation->edits()->with(['chief:id,name,employee_id', 'auditLog:id,action,this_hash,created_at', 'block'])->get(),
            'commits' => $consolidation->commits()->with(['chief:id,name,employee_id', 'auditLog:id,action,this_hash,created_at'])->get(),
            'workflow' => SlotWorkflowEvent::query()
                ->whereHas('slotAssignment', fn ($query) => $query->whereIn('slot_id', $consolidation->slotIdsInWindow()))
                ->with(['actor:id,name,employee_id', 'auditLog:id,action,this_hash,created_at'])
                ->latest('created_at')
                ->get(),
        ];
    }

    private function summary(ChiefConsolidation $consolidation): array
    {
        $consolidation->loadMissing(['sitting', 'commits']);

        return [
            'id' => $consolidation->id,
            'sitting_id' => $consolidation->sitting_id,
            'window_code' => $consolidation->window_code,
            'starts_at_offset_ms' => $consolidation->starts_at_offset_ms,
            'duration_ms' => $consolidation->duration_ms,
            'status' => $consolidation->status,
            'inputs_ready' => $consolidation->inputsReady(),
            'sitting' => $consolidation->sitting,
            'commits' => $consolidation->commits,
            'block_count' => $consolidation->blocks()->count(),
        ];
    }

    private function detail(ChiefConsolidation $consolidation): array
    {
        $consolidation->load(['sitting', 'commits.chief:id,name,employee_id', 'speakerOverrides', 'edits.auditLog:id,action,this_hash,created_at']);
        $blocks = $consolidation->blocks()
            ->with(['slot.assignments.user:id,name,employee_id,section', 'member', 'customMember'])
            ->get();

        return [
            'consolidation' => [
                ...$this->summary($consolidation),
                'blocks' => $blocks,
                'edits' => $consolidation->edits,
                'speaker_overrides' => $consolidation->speakerOverrides,
            ],
        ];
    }

    private function blockInConsolidation(ChiefConsolidation $consolidation, Block $block): bool
    {
        $block->loadMissing('slot');

        return $block->slot
            && $block->slot->sitting_id === $consolidation->sitting_id
            && $block->slot->start_offset_ms >= $consolidation->starts_at_offset_ms
            && $block->slot->start_offset_ms < $consolidation->starts_at_offset_ms + $consolidation->duration_ms;
    }

    private function langSide(User $user): string
    {
        $side = $user->lang_role ?? ($user->language_competencies[0] ?? null);

        return in_array($side, ['en', 'hi'], true) ? $side : 'en';
    }

    private function nextCommitStatus(ChiefConsolidation $consolidation, string $side): string
    {
        $langs = $consolidation->commits()->pluck('lang_side')->push($side)->unique()->values()->all();

        if (in_array('en', $langs, true) && in_array('hi', $langs, true)) {
            return 'dual_committed';
        }

        return $side === 'en' ? 'en_committed' : 'hi_committed';
    }

    private function speakerSnapshot(Block $block): array
    {
        $block->loadMissing(['member', 'customMember']);

        return [
            'member_id' => $block->member_id,
            'custom_member_id' => $block->custom_member_id,
            'name_en' => $block->member?->name_en ?? $block->customMember?->name_en,
            'name_hi' => $block->member?->name_hi ?? $block->customMember?->name_hi,
        ];
    }

    private function primaryRole(User $user): string
    {
        return $user->roles()->value('name') ?? 'authenticated';
    }
}
