<?php

namespace App\Modules\Translator\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Translator\Models\TranslatorAssignment;
use App\Modules\Translator\Models\TranslatorCommit;
use App\Modules\Translator\Models\TranslatorEdit;
use App\Modules\Translator\Models\TranslatorGlossary;
use App\Modules\Translator\Services\TranslatorAiAssistService;
use App\Modules\Translator\Services\VersionFinalisationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TranslatorController extends Controller
{
    public function queue(Request $request)
    {
        return TranslatorAssignment::query()
            ->with(['slot.sitting', 'translator:id,name,employee_id'])
            ->where('translator_user_id', $request->user()->id)
            ->whereIn('language_pair', $request->user()->language_competencies ?? [])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function show(Request $request, TranslatorAssignment $assignment)
    {
        $this->authorizeAssignment($request, $assignment);

        return $this->assignmentPayload($assignment);
    }

    public function slotDraft(Request $request, Slot $slot)
    {
        $assignment = $this->assignmentForSlot($request, $slot);

        return $this->slotDraftPayload($assignment);
    }

    public function patchSlotDraft(Request $request, Slot $slot, AuditLogger $audit)
    {
        $assignment = $this->assignmentForSlot($request, $slot);
        abort_if($this->isSealed($assignment), 423, 'Assignment is sealed.');

        $data = $request->validate([
            'slot_version' => ['required', 'integer', 'min:0'],
            'edits' => ['required', 'array', 'min:1'],
            'edits.*.block_id' => ['required', 'integer', 'exists:blocks,id'],
            'edits.*.text' => ['required', 'string'],
            'edits.*.kind' => ['nullable', Rule::in(['text', 'terminology', 'attribution'])],
        ]);

        return DB::transaction(function () use ($assignment, $data, $audit) {
            $locked = TranslatorAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $currentVersion = $this->slotVersion($locked);
            if ((int) $data['slot_version'] !== $currentVersion) {
                return response()->json([
                    'message' => 'Version conflict.',
                    'current_slot_version' => $currentVersion,
                    'draft' => $this->slotDraftPayload($locked),
                ], 409);
            }

            foreach ($data['edits'] as $edit) {
                /** @var Block $block */
                $block = Block::query()
                    ->where('slot_id', $locked->slot_id)
                    ->whereKey($edit['block_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $before = (string) ($block->translated_text ?: $block->ai_text ?: $block->text);
                $block->forceFill([
                    'translated_text' => $edit['text'],
                    'version' => $block->version + 1,
                ])->save();

                $auditLog = $audit->log('translator.slot_draft.patch', $block, [
                    'assignment_id' => $locked->id,
                    'slot_id' => $locked->slot_id,
                    'before' => $before,
                    'after' => $edit['text'],
                ]);

                TranslatorEdit::query()->create([
                    'assignment_id' => $locked->id,
                    'block_id' => $block->id,
                    'kind' => $edit['kind'] ?? 'text',
                    'ai_suggestion' => $block->ai_text,
                    'before' => $before,
                    'after' => $edit['text'],
                    'audit_log_id' => $auditLog->id,
                ]);
            }

            $locked->forceFill(['status' => 'in_review'])->save();

            return $this->slotDraftPayload($locked->fresh());
        });
    }

    public function commitSlotDraft(Request $request, Slot $slot, AuditLogger $audit)
    {
        $assignment = $this->assignmentForSlot($request, $slot);
        abort_if($this->isSealed($assignment), 422, 'Assignment already committed.');

        return DB::transaction(function () use ($assignment, $audit, $request) {
            $locked = TranslatorAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $auditLog = $audit->log('translator.slot_draft.commit', $locked, [
                'slot_id' => $locked->slot_id,
                'language_pair' => $locked->language_pair,
                'next_stage' => 'reviewer_vetter',
            ]);

            $commit = TranslatorCommit::query()->create([
                'assignment_id' => $locked->id,
                'translator_user_id' => $request->user()->id,
                'block_count' => $locked->blocks()->count(),
                'edit_count' => $locked->edits()->count(),
                'ai_acceptance_rate' => $locked->aiAcceptanceRate(),
                'committed_at' => now(),
                'committed_audit_log_id' => $auditLog->id,
            ]);

            $locked->forceFill(['status' => 'forwarded'])->save();

            return [
                ...$this->slotDraftPayload($locked->fresh()),
                'commit' => $commit->fresh(),
            ];
        });
    }

    public function requestAi(Request $request, TranslatorAssignment $assignment, TranslatorAiAssistService $service)
    {
        $this->authorizeAssignment($request, $assignment);

        abort_unless($assignment->isReady(), 422, 'Assignment is not ready for AI assist.');

        return $this->assignmentPayload($service->requestAi($assignment));
    }

    public function updateBlock(Request $request, TranslatorAssignment $assignment, Block $block, AuditLogger $audit)
    {
        $this->authorizeAssignment($request, $assignment);
        $data = $request->validate([
            'text' => ['required', 'string'],
            'version' => ['required', 'integer'],
            'kind' => ['nullable', Rule::in(['text', 'terminology', 'attribution'])],
        ]);

        abort_if($this->isSealed($assignment), 423, 'Assignment is sealed.');
        abort_unless((int) $block->slot_id === (int) $assignment->slot_id, 404);

        return DB::transaction(function () use ($block, $assignment, $data, $audit) {
            $locked = Block::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->version !== (int) $data['version']) {
                return response()->json(['message' => 'Version conflict.', 'current' => $locked], 409);
            }

            $before = (string) ($locked->translated_text ?: $locked->text);
            $locked->forceFill([
                'translated_text' => $data['text'],
                'version' => $locked->version + 1,
            ])->save();

            $auditLog = $audit->log('translator.block.edit', $locked, [
                'assignment_id' => $assignment->id,
                'before' => $before,
                'after' => $data['text'],
            ]);

            TranslatorEdit::query()->create([
                'assignment_id' => $assignment->id,
                'block_id' => $locked->id,
                'kind' => $data['kind'] ?? 'text',
                'ai_suggestion' => $locked->ai_text,
                'before' => $before,
                'after' => $data['text'],
                'audit_log_id' => $auditLog->id,
            ]);

            return $locked->fresh();
        });
    }

    public function acceptAi(Request $request, TranslatorAssignment $assignment, Block $block, AuditLogger $audit)
    {
        $this->authorizeAssignment($request, $assignment);
        abort_if($this->isSealed($assignment), 423, 'Assignment is sealed.');
        abort_unless((int) $block->slot_id === (int) $assignment->slot_id, 404);
        abort_unless(filled($block->ai_text), 422, 'No AI suggestion exists for this block.');

        return DB::transaction(function () use ($block, $assignment, $audit) {
            $locked = Block::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $before = (string) ($locked->translated_text ?: $locked->text);
            $locked->forceFill([
                'translated_text' => $locked->ai_text,
                'version' => $locked->version + 1,
            ])->save();

            $auditLog = $audit->log('translator.block.accept_ai', $locked, [
                'assignment_id' => $assignment->id,
                'ai_suggestion' => $locked->ai_text,
            ]);

            TranslatorEdit::query()->create([
                'assignment_id' => $assignment->id,
                'block_id' => $locked->id,
                'kind' => 'text',
                'ai_suggestion' => $locked->ai_text,
                'before' => $before,
                'after' => $locked->ai_text,
                'audit_log_id' => $auditLog->id,
            ]);

            return $locked->fresh();
        });
    }

    public function commit(Request $request, TranslatorAssignment $assignment, AuditLogger $audit)
    {
        $this->authorizeAssignment($request, $assignment);
        abort_if($this->isSealed($assignment), 422, 'Assignment already committed.');

        return DB::transaction(function () use ($assignment, $audit, $request) {
            $locked = TranslatorAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $auditLog = $audit->log('translator.assignment.commit', $locked, [
                'language_pair' => $locked->language_pair,
                'next_stage' => 'reviewer_vetter',
            ]);

            $commit = TranslatorCommit::query()->create([
                'assignment_id' => $locked->id,
                'translator_user_id' => $request->user()->id,
                'block_count' => $locked->blocks()->count(),
                'edit_count' => $locked->edits()->count(),
                'ai_acceptance_rate' => $locked->aiAcceptanceRate(),
                'committed_at' => now(),
                'committed_audit_log_id' => $auditLog->id,
            ]);

            $locked->forceFill(['status' => 'forwarded'])->save();

            return ['assignment' => $locked->fresh(), 'commit' => $commit->fresh()];
        });
    }

    public function finaliseSlotDraft(Request $request, Slot $slot, VersionFinalisationService $service)
    {
        $assignment = $this->assignmentForSlot($request, $slot);
        $data = $request->validate([
            'slot_version' => ['required', 'integer', 'min:0'],
            'draft_type' => ['required', Rule::in(['hv', 'ev'])],
            'regional_language_flag' => ['sometimes', 'boolean'],
            'regional_language_code' => ['nullable', 'required_if:regional_language_flag,true', 'string', 'max:16'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $service->finaliseDraft($assignment, $request->user(), $data);

        return response()->json($result['body'], $result['status']);
    }

    public function forwardToSupervisor(Request $request, TranslatorAssignment $assignment, VersionFinalisationService $service)
    {
        $this->authorizeAssignment($request, $assignment);
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $service->forwardToSupervisor($assignment, $request->user(), $data['note'] ?? null);

        return response()->json($result['body'], $result['status']);
    }

    public function forwardToDirector(Request $request, TranslatorAssignment $assignment, VersionFinalisationService $service)
    {
        $this->authorizeReviewer($request, $assignment);
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $service->forwardToDirector($assignment, $request->user(), $data['note'] ?? null);

        return response()->json($result['body'], $result['status']);
    }

    public function reviewerQueue(Request $request)
    {
        $user = $request->user();

        return TranslatorAssignment::query()
            ->with(['slot.sitting', 'translator:id,name,employee_id'])
            ->where('status', 'supervisor_review')
            ->get()
            ->filter(fn (TranslatorAssignment $assignment) => $this->reviewerCanAccess($user, $assignment))
            ->values();
    }

    public function reviewerShow(Request $request, TranslatorAssignment $assignment)
    {
        $this->authorizeReviewer($request, $assignment);

        return [
            ...$this->assignmentPayload($assignment),
            'history' => $this->historyPayload($assignment),
        ];
    }

    public function reviewerReturn(Request $request, TranslatorAssignment $assignment, AuditLogger $audit)
    {
        $this->authorizeReviewer($request, $assignment);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10'],
        ]);

        return DB::transaction(function () use ($request, $assignment, $audit, $data) {
            /** @var TranslatorAssignment $locked */
            $locked = TranslatorAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'supervisor_review') {
                return response()->json(['message' => 'Assignment must be in supervisor review before return.'], 422);
            }

            $auditLog = $audit->log('translator.reviewer.return', $locked, [
                'from_stage' => 'supervisor',
                'to_stage' => 'translator',
                'reason' => $data['reason'],
                'reviewer_id' => $request->user()->id,
            ]);

            $locked->forceFill([
                'status' => 'returned',
                'ai_translation_meta' => array_replace_recursive($locked->ai_translation_meta ?? [], [
                    'current_stage' => 'translator',
                    'return_reason' => $data['reason'],
                    'return_to_stage' => 'translator',
                    'reviewer_return' => [
                        'returned_by' => $request->user()->id,
                        'returned_at' => now()->toIso8601String(),
                        'audit_log_id' => $auditLog->id,
                    ],
                ]),
            ])->save();

            return ['assignment' => $locked->fresh()];
        });
    }

    public function return(Request $request, TranslatorAssignment $assignment, AuditLogger $audit)
    {
        $this->authorizeAssignment($request, $assignment);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10'],
            'to_stage' => ['required', 'string', 'max:64'],
        ]);

        return DB::transaction(function () use ($assignment, $audit, $data) {
            $locked = TranslatorAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $auditLog = $audit->log('translator.assignment.return', $locked, $data);
            $locked->forceFill([
                'status' => 'returned',
                'ai_translation_meta' => array_merge($locked->ai_translation_meta ?? [], [
                    'return_reason' => $data['reason'],
                    'return_to_stage' => $data['to_stage'],
                    'return_audit_log_id' => $auditLog->id,
                ]),
            ])->save();

            return $locked->fresh();
        });
    }

    public function history(Request $request, TranslatorAssignment $assignment)
    {
        $this->authorizeAssignment($request, $assignment);

        return $this->historyPayload($assignment);
    }

    public function glossary(Request $request)
    {
        $query = TranslatorGlossary::query()->orderBy('term_source');
        if ($request->filled('language_pair')) {
            $query->where('language_pair', $request->string('language_pair')->toString());
        }

        return $query->get();
    }

    public function storeGlossary(Request $request, AuditLogger $audit)
    {
        $data = $this->glossaryData($request);
        $term = TranslatorGlossary::query()->create($data + [
            'created_by' => $request->user()->id,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        $audit->log('translator.glossary.create', $term, $term->toArray());

        return response()->json($term->fresh(), 201);
    }

    public function updateGlossary(Request $request, TranslatorGlossary $glossary, AuditLogger $audit)
    {
        $data = $this->glossaryData($request);
        $before = $glossary->toArray();
        $glossary->forceFill($data + ['approved_by' => $request->user()->id, 'approved_at' => now()])->save();
        $audit->log('translator.glossary.update', $glossary, ['before' => $before, 'after' => $glossary->fresh()->toArray()]);

        return $glossary->fresh();
    }

    private function authorizeAssignment(Request $request, TranslatorAssignment $assignment): void
    {
        abort_unless((int) $assignment->translator_user_id === (int) $request->user()->id, 403);
        abort_unless(in_array($assignment->language_pair, $request->user()->language_competencies ?? [], true), 403);
    }

    private function authorizeReviewer(Request $request, TranslatorAssignment $assignment): void
    {
        $user = $request->user();
        abort_unless($user?->isSupervisor() || $user?->isAdmin(), 403);

        abort_unless($this->reviewerCanAccess($user, $assignment), 403);
    }

    private function reviewerCanAccess(?\App\Modules\Core\Models\User $user, TranslatorAssignment $assignment): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $competencies = $user->language_competencies ?? [];
        $parts = explode('_to_', $assignment->language_pair);

        return ($user->isSupervisor())
            && (
                in_array($assignment->language_pair, $competencies, true)
            || in_array($parts[0] ?? '', $competencies, true)
            || in_array($parts[1] ?? '', $competencies, true)
            );
    }

    private function assignmentForSlot(Request $request, Slot $slot): TranslatorAssignment
    {
        /** @var TranslatorAssignment $assignment */
        $assignment = TranslatorAssignment::query()
            ->where('slot_id', $slot->id)
            ->where('translator_user_id', $request->user()->id)
            ->whereIn('language_pair', $request->user()->language_competencies ?? [])
            ->firstOrFail();

        return $assignment;
    }

    private function assignmentPayload(TranslatorAssignment $assignment): array
    {
        return [
            'assignment' => $assignment->load(['slot.sitting', 'translator:id,name,employee_id']),
            'blocks' => $assignment->blocks()->with(['member', 'customMember'])->get(),
            'glossary' => TranslatorGlossary::query()->where('language_pair', $assignment->language_pair)->orderBy('term_source')->get(),
        ];
    }

    private function slotDraftPayload(TranslatorAssignment $assignment): array
    {
        return [
            ...$this->assignmentPayload($assignment),
            'slot_version' => $this->slotVersion($assignment),
            'review_mode' => 'single_editor',
            'collaboration_required' => false,
        ];
    }

    private function slotVersion(TranslatorAssignment $assignment): int
    {
        $stats = $assignment->blocks()
            ->selectRaw('COUNT(*) as block_count, COALESCE(SUM(version), 0) as version_sum, COALESCE(MIN(version), 0) as min_version')
            ->first();

        $count = (int) ($stats->block_count ?? 0);
        $sum = (int) ($stats->version_sum ?? 0);
        $min = (int) ($stats->min_version ?? 0);

        if ($count === 0) {
            return 0;
        }

        return $min <= 0 ? $sum : $sum - $count + 1;
    }

    private function historyPayload(TranslatorAssignment $assignment): array
    {
        return [
            'edits' => $assignment->edits()->with('auditLog:id,action,this_hash,created_at')->latest()->get(),
            'audit' => AuditLog::query()
                ->where('subject_type', $assignment::class)
                ->where('subject_id', $assignment->id)
                ->orWhere(fn ($query) => $query->where('action', 'like', 'translator.block.%')
                    ->whereIn('subject_id', $assignment->blocks()->pluck('id')))
                ->orWhere(fn ($query) => $query->where('action', 'like', 'translator.reviewer.%')
                    ->where('subject_id', $assignment->id))
                ->orderBy('id')
                ->get(),
        ];
    }

    private function isSealed(TranslatorAssignment $assignment): bool
    {
        return in_array($assignment->status, VersionFinalisationService::SEALED_STATUSES, true);
    }

    private function glossaryData(Request $request): array
    {
        return $request->validate([
            'term_source' => ['required', 'string', 'max:255'],
            'term_target' => ['required', 'string', 'max:255'],
            'language_pair' => ['required', 'string', 'max:24'],
            'domain' => ['required', Rule::in(['parliamentary', 'economic', 'legal'])],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
