<?php

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\SlotWorkflowEvent;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Js\Models\ExpungeCandidate;
use App\Modules\Js\Models\JsDecision;
use App\Modules\Js\Models\JsSgHandoff;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Js\Models\SuggestedEdit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::prefix('js')->middleware(['auth:sanctum', 'role:js'])->group(function () {
    Route::get('/queue', function () {
        return JsWindow::query()
            ->with(['sitting', 'handoffs'])
            ->whereIn('status', ['open', 'under_review', 'sent_to_sg', 'sg_returned'])
            ->orderBy('sitting_id')
            ->orderBy('starts_at_offset_ms')
            ->get()
            ->filter(fn (JsWindow $window) => js_window_ready($window))
            ->values()
            ->map(fn (JsWindow $window) => js_window_summary($window));
    });

    Route::get('/windows/{window}', fn (JsWindow $window) => ['window' => js_window_detail($window)]);

    Route::get('/windows/{window}/suggested-edits', fn (JsWindow $window) => $window->suggestedEdits()
        ->with(['block.member', 'block.customMember', 'sourceMember'])
        ->orderByRaw("CASE state WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END")
        ->orderBy('id')
        ->get());

    Route::get('/windows/{window}/expunge-candidates', fn (JsWindow $window) => $window->expungeCandidates()
        ->with(['block.member', 'block.customMember'])
        ->orderByRaw("CASE state WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 ELSE 2 END")
        ->orderBy('id')
        ->get());

    Route::post('/windows/{window}/suggested-edits/{suggestedEdit}/accept', function (
        Request $request,
        JsWindow $window,
        SuggestedEdit $suggestedEdit,
        AuditLogger $audit
    ) {
        js_abort_unless_child($window, $suggestedEdit);

        return DB::transaction(function () use ($request, $window, $suggestedEdit, $audit) {
            /** @var SuggestedEdit $edit */
            $edit = SuggestedEdit::query()->whereKey($suggestedEdit->id)->lockForUpdate()->firstOrFail();
            /** @var Block $block */
            $block = Block::query()->whereKey($edit->block_id)->lockForUpdate()->firstOrFail();

            if ($edit->state !== 'pending') {
                return response()->json(['message' => 'Suggested edit already decided.'], 409);
            }

            $before = $block->text;
            $block->forceFill(['text' => $edit->after, 'version' => $block->version + 1])->save();
            $edit->forceFill(['state' => 'accepted'])->save();
            $window->forceFill(['status' => 'under_review'])->save();

            $auditLog = $audit->log('js.se.accept', $edit, [
                'window_id' => $window->id,
                'block_id' => $block->id,
                'before_excerpt' => mb_substr($before, 0, 120),
                'after_excerpt' => mb_substr($edit->after, 0, 120),
            ]);

            js_decision($request, $window, 'accept_se', [
                'suggested_edit_id' => $edit->id,
                'block_id' => $block->id,
                'before' => $before,
                'after' => $edit->after,
            ], $auditLog->id);

            return ['suggested_edit' => $edit->fresh(['block.member', 'block.customMember']), 'block' => $block->fresh(['member', 'customMember'])];
        });
    });

    Route::post('/windows/{window}/suggested-edits/{suggestedEdit}/decline', function (
        Request $request,
        JsWindow $window,
        SuggestedEdit $suggestedEdit,
        AuditLogger $audit
    ) {
        js_abort_unless_child($window, $suggestedEdit);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return DB::transaction(function () use ($request, $window, $suggestedEdit, $validated, $audit) {
            /** @var SuggestedEdit $edit */
            $edit = SuggestedEdit::query()->whereKey($suggestedEdit->id)->lockForUpdate()->firstOrFail();

            if ($edit->state !== 'pending') {
                return response()->json(['message' => 'Suggested edit already decided.'], 409);
            }

            $edit->forceFill(['state' => 'declined'])->save();
            $window->forceFill(['status' => 'under_review'])->save();
            $auditLog = $audit->log('js.se.decline', $edit, [
                'window_id' => $window->id,
                'block_id' => $edit->block_id,
                'note' => $validated['note'] ?? null,
            ]);

            js_decision($request, $window, 'decline_se', [
                'suggested_edit_id' => $edit->id,
                'note' => $validated['note'] ?? null,
            ], $auditLog->id);

            return ['suggested_edit' => $edit->fresh(['block.member', 'block.customMember'])];
        });
    });

    Route::put('/windows/{window}/blocks/{block}', function (Request $request, JsWindow $window, Block $block, AuditLogger $audit) {
        $validated = $request->validate([
            'text' => ['required', 'string'],
            'version' => ['required', 'integer', 'min:1'],
        ]);
        js_abort_unless_block_in_window($window, $block);

        return DB::transaction(function () use ($request, $window, $block, $validated, $audit) {
            /** @var Block $locked */
            $locked = Block::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();

            if ((int) $validated['version'] !== $locked->version) {
                return response()->json(['current_version' => $locked->version, 'current_text' => $locked->text], 409);
            }

            $before = $locked->text;
            $locked->forceFill(['text' => $validated['text'], 'version' => $locked->version + 1])->save();
            $window->forceFill(['status' => 'under_review'])->save();
            $auditLog = $audit->log('js.block.text', $locked, [
                'window_id' => $window->id,
                'before_excerpt' => mb_substr($before, 0, 120),
                'after_excerpt' => mb_substr($validated['text'], 0, 120),
            ]);

            js_decision($request, $window, 'text_edit', [
                'block_id' => $locked->id,
                'before' => $before,
                'after' => $validated['text'],
            ], $auditLog->id);

            return $locked->fresh(['member', 'customMember']);
        });
    });

    Route::put('/windows/{window}/blocks/{block}/speaker', function (Request $request, JsWindow $window, Block $block, AuditLogger $audit) {
        $validated = $request->validate([
            'member_id' => ['nullable', 'integer', 'exists:members,id', 'required_without:custom_member_id'],
            'custom_member_id' => ['nullable', 'integer', 'exists:member_customs,id', 'required_without:member_id'],
        ]);
        js_abort_unless_block_in_window($window, $block);

        return DB::transaction(function () use ($request, $window, $block, $validated, $audit) {
            /** @var Block $locked */
            $locked = Block::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $before = js_speaker_snapshot($locked);
            $locked->forceFill([
                'member_id' => $validated['member_id'] ?? null,
                'custom_member_id' => $validated['custom_member_id'] ?? null,
            ])->save();
            $after = js_speaker_snapshot($locked->fresh(['member', 'customMember']));
            $window->forceFill(['status' => 'under_review'])->save();

            $auditLog = $audit->log('js.block.speaker', $locked, ['window_id' => $window->id, 'before' => $before, 'after' => $after]);
            js_decision($request, $window, 'speaker_override', ['block_id' => $locked->id, 'before' => $before, 'after' => $after], $auditLog->id);

            return $locked->fresh(['member', 'customMember']);
        });
    });

    Route::post('/windows/{window}/expunge-candidates/{candidate}/confirm', function (Request $request, JsWindow $window, ExpungeCandidate $candidate, AuditLogger $audit) {
        return js_decide_expunge($request, $window, $candidate, 'confirmed', 'expunge_confirm', $audit);
    });

    Route::post('/windows/{window}/expunge-candidates/{candidate}/override', function (Request $request, JsWindow $window, ExpungeCandidate $candidate, AuditLogger $audit) {
        return js_decide_expunge($request, $window, $candidate, 'overridden', 'expunge_override', $audit);
    });

    Route::post('/windows/{window}/forward-sg', function (Request $request, JsWindow $window, AuditLogger $audit) {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return DB::transaction(function () use ($window, $validated, $audit) {
            /** @var JsWindow $locked */
            $locked = JsWindow::query()->whereKey($window->id)->lockForUpdate()->firstOrFail();
            $auditLog = $audit->log('js.window.forward_sg', $locked, [
                'note' => $validated['note'] ?? null,
                'pending_suggested_edits' => $locked->suggestedEditsCount('pending'),
                'pending_expunges' => $locked->expungeCandidatesCount('pending'),
            ]);

            JsSgHandoff::query()->create([
                'window_id' => $locked->id,
                'sent_at' => now(),
                'sent_audit_log_id' => $auditLog->id,
                'confirmed_expunges' => $locked->expungeCandidatesCount('confirmed'),
                'manual_expunges' => 0,
            ]);
            $locked->forceFill(['status' => 'sent_to_sg'])->save();

            return ['window' => js_window_detail($locked->fresh())];
        });
    });

    Route::post('/windows/{window}/sg-return-simulate', function (Request $request, JsWindow $window, AuditLogger $audit) {
        return DB::transaction(function () use ($request, $window, $audit) {
            /** @var JsWindow $locked */
            $locked = JsWindow::query()->whereKey($window->id)->lockForUpdate()->firstOrFail();
            $handoff = $locked->handoffs()->whereNull('returned_at')->latest()->first();

            if (! $handoff) {
                return response()->json(['message' => 'Window has not been sent to SG.'], 422);
            }

            $sg = User::query()->role('sg')->first() ?? $request->user();
            $auditLog = $audit->log('js.window.sg_return', $locked, [
                'sg_user_id' => $sg->id,
                'dsc_serial' => 'DSC-SG-SIM-2026',
                'confirmed_expunges' => $locked->expungeCandidatesCount('confirmed'),
            ]);
            $handoff->forceFill([
                'returned_at' => now(),
                'returned_audit_log_id' => $auditLog->id,
                'dsc_serial' => 'DSC-SG-SIM-2026',
                'sg_user_id' => $sg->id,
                'confirmed_expunges' => $locked->expungeCandidatesCount('confirmed'),
            ])->save();
            $locked->forceFill(['status' => 'sg_returned'])->save();

            return ['window' => js_window_detail($locked->fresh())];
        });
    });

    Route::post('/windows/{window}/approve-publish', function (Request $request, JsWindow $window, AuditLogger $audit) {
        return DB::transaction(function () use ($request, $window, $audit) {
            /** @var JsWindow $locked */
            $locked = JsWindow::query()->whereKey($window->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['sg_returned', 'under_review'], true)) {
                return response()->json(['message' => 'Window must be under review or returned by SG before Director handoff.'], 422);
            }

            $auditLog = $audit->log('js.window.approve', $locked, [
                'director_handoff' => true,
                'actor_id' => $request->user()->id,
                'confirmed_expunges' => $locked->expungeCandidatesCount('confirmed'),
            ]);
            $locked->forceFill(['status' => 'approved'])->save();

            ChiefConsolidation::query()
                ->whereIn('id', $locked->bothChiefHalves()->pluck('id'))
                ->update(['status' => 'forwarded_to_js', 'updated_at' => now()]);

            return ['window' => js_window_detail($locked->fresh()), 'audit_log_id' => $auditLog->id];
        });
    });

    Route::post('/windows/{window}/return', function (Request $request, JsWindow $window, AuditLogger $audit) {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'to_stage' => ['required', Rule::in(['chief', 'supervisor', 'reporter'])],
        ]);

        return DB::transaction(function () use ($request, $window, $validated, $audit) {
            /** @var JsWindow $locked */
            $locked = JsWindow::query()->whereKey($window->id)->lockForUpdate()->firstOrFail();
            $auditLog = $audit->log('js.window.return', $locked, $validated);
            $chiefIds = $locked->bothChiefHalves()->pluck('id');

            ChiefConsolidation::query()->whereIn('id', $chiefIds)->update(['status' => 'open', 'updated_at' => now()]);

            if ($validated['to_stage'] !== 'chief') {
                $slotIds = $locked->blocks()->pluck('slot_id')->unique()->values();
                SlotAssignment::query()
                    ->whereIn('slot_id', $slotIds)
                    ->whereIn('workflow_stage', ['chief', 'returned'])
                    ->each(function (SlotAssignment $assignment) use ($request, $validated, $auditLog) {
                        $assignment->forceFill([
                            'workflow_stage' => $validated['to_stage'],
                            'status' => $validated['to_stage'] === 'reporter' ? 'in_progress' : $assignment->status,
                            'committed_at' => $validated['to_stage'] === 'reporter' ? null : $assignment->committed_at,
                            'committed_audit_log_id' => $validated['to_stage'] === 'reporter' ? null : $assignment->committed_audit_log_id,
                            'last_workflow_action_at' => now(),
                        ])->save();

                        SlotWorkflowEvent::query()->create([
                            'slot_assignment_id' => $assignment->id,
                            'from_stage' => 'js',
                            'to_stage' => $validated['to_stage'],
                            'action' => 'return',
                            'actor_id' => $request->user()->id,
                            'actor_role' => 'js',
                            'reason' => $validated['reason'],
                            'audit_log_id' => $auditLog->id,
                            'created_at' => now(),
                        ]);
                    });
            }

            $locked->forceFill(['status' => 'open'])->save();

            return ['window' => js_window_detail($locked->fresh())];
        });
    });

    Route::get('/windows/{window}/history', function (JsWindow $window) {
        return [
            'decisions' => $window->decisions()->with(['actor:id,name,employee_id', 'auditLog:id,action,this_hash,created_at'])->get(),
            'handoffs' => $window->handoffs()->with(['sgUser:id,name,employee_id', 'sentAuditLog:id,action,this_hash,created_at', 'returnedAuditLog:id,action,this_hash,created_at'])->get(),
            'chief_halves' => $window->bothChiefHalves()->with(['commits.chief:id,name,employee_id'])->get(),
        ];
    });
});

if (! function_exists('js_window_ready')) {
function js_window_ready(JsWindow $window): bool
{
    return $window->bothChiefHalves()->where('status', 'dual_committed')->count() === 2;
}
}

if (! function_exists('js_window_summary')) {
function js_window_summary(JsWindow $window): array
{
    return [
        ...$window->only(['id', 'sitting_id', 'window_code', 'starts_at_offset_ms', 'duration_ms', 'status', 'created_at', 'updated_at']),
        'sitting' => $window->relationLoaded('sitting') ? $window->sitting : null,
        'chief_halves_count' => $window->bothChiefHalves()->where('status', 'dual_committed')->count(),
        'block_count' => $window->blocks()->count(),
        'suggested_edits_count' => $window->suggestedEditsCount(),
        'pending_suggested_edits_count' => $window->suggestedEditsCount('pending'),
        'expunge_candidates_count' => $window->expungeCandidatesCount(),
        'pending_expunge_candidates_count' => $window->expungeCandidatesCount('pending'),
        'latest_handoff' => $window->handoffs()->latest()->first(),
    ];
}
}

if (! function_exists('js_window_detail')) {
function js_window_detail(JsWindow $window): array
{
    $window->load(['sitting']);

    return [
        ...js_window_summary($window),
        'blocks' => $window->blocks()->get(),
        'suggested_edits' => $window->suggestedEdits()->with(['block', 'sourceMember'])->orderBy('id')->get(),
        'expunge_candidates' => $window->expungeCandidates()->with(['block'])->orderBy('id')->get(),
        'handoffs' => $window->handoffs()->with(['sgUser:id,name,employee_id'])->get(),
    ];
}
}

if (! function_exists('js_decision')) {
function js_decision(Request $request, JsWindow $window, string $kind, array $payload, int $auditLogId): JsDecision
{
    return JsDecision::query()->create([
        'window_id' => $window->id,
        'kind' => $kind,
        'actor_id' => $request->user()->id,
        'payload' => $payload,
        'audit_log_id' => $auditLogId,
    ]);
}
}

if (! function_exists('js_abort_unless_child')) {
function js_abort_unless_child(JsWindow $window, SuggestedEdit|ExpungeCandidate $child): void
{
    if ((int) $child->window_id !== (int) $window->id) {
        abort(404);
    }
}
}

if (! function_exists('js_abort_unless_block_in_window')) {
function js_abort_unless_block_in_window(JsWindow $window, Block $block): void
{
    $exists = $window->blocks()->where('blocks.id', $block->id)->exists();

    if (! $exists) {
        abort(404);
    }
}
}

if (! function_exists('js_speaker_snapshot')) {
function js_speaker_snapshot(Block $block): array
{
    $speaker = $block->member ?? $block->customMember;

    return [
        'member_id' => $block->member_id,
        'custom_member_id' => $block->custom_member_id,
        'name_en' => $speaker?->name_en,
        'name_hi' => $speaker?->name_hi,
    ];
}
}

if (! function_exists('js_decide_expunge')) {
function js_decide_expunge(Request $request, JsWindow $window, ExpungeCandidate $candidate, string $state, string $kind, AuditLogger $audit)
{
    js_abort_unless_child($window, $candidate);

    return DB::transaction(function () use ($request, $window, $candidate, $state, $kind, $audit) {
        /** @var ExpungeCandidate $locked */
        $locked = ExpungeCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
        $locked->forceFill(['state' => $state])->save();
        $window->forceFill(['status' => 'under_review'])->save();

        $auditLog = $audit->log($kind === 'expunge_confirm' ? 'js.expunge.confirm' : 'js.expunge.override', $locked, [
            'window_id' => $window->id,
            'block_id' => $locked->block_id,
            'word' => $locked->word,
            'state' => $state,
        ]);
        js_decision($request, $window, $kind, [
            'candidate_id' => $locked->id,
            'block_id' => $locked->block_id,
            'word' => $locked->word,
            'state' => $state,
        ], $auditLog->id);

        return ['candidate' => $locked->fresh(['block.member', 'block.customMember'])];
    });
}
}
