<?php

use App\Modules\Capture\Requests\BlockUpdateRequest;
use App\Modules\Capture\Requests\CustomMemberRequest;
use App\Modules\Capture\Requests\MemberIndexRequest;
use App\Modules\Capture\Requests\SlotCommitRequest;
use App\Modules\Capture\Requests\SpeakerUpdateRequest;
use App\Modules\Capture\Requests\SupervisorQueueRequest;
use App\Modules\Capture\Requests\WorkflowForwardRequest;
use App\Modules\Capture\Requests\WorkflowReturnRequest;
use App\Modules\Capture\Services\ReporterAudioFinalizer;
use App\Modules\Capture\Services\ReporterAudioStorage;
use App\Modules\Reporter\Controllers\SlotController as ReporterSlotController;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\MemberCustom;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\SlotWorkflowEvent;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sittings/live', function () {
        return Sitting::query()
            ->where('status', 'live')
            ->withCount('slots')
            ->orderBy('sitting_date')
            ->get();
    });

    Route::get('/me/assignments', function (Request $request) {
        return SlotAssignment::query()
            ->where('user_id', $request->user()->id)
            ->with(['slot.sitting', 'workflowEvents.actor:id,name,employee_id'])
            ->whereHas('slot.sitting', fn ($query) => $query
                ->whereDate('sitting_date', today())
                ->orWhere('status', 'live'))
            ->orderBy('slot_id')
            ->get()
            ->map(function (SlotAssignment $assignment) {
                return [
                    'id' => $assignment->id,
                    'lang_role' => $assignment->lang_role,
                    'status' => $assignment->status,
                    'workflow_stage' => $assignment->workflow_stage,
                    'committed_at' => $assignment->committed_at,
                    'latest_return_event' => $assignment->workflow_stage === 'returned'
                        ? workflowEventPayload($assignment->workflowEvents->firstWhere('action', 'return'))
                        : null,
                    'slot' => [
                        ...$assignment->slot->toArray(),
                        'block_count' => $assignment->slot->blocks()->count(),
                        'sitting' => $assignment->slot->sitting,
                    ],
                ];
            });
    });

    Route::get('/slots/{slot}', function (Slot $slot) {
        return $slot->load([
            'sitting',
            'assignments.user:id,name,employee_id',
            'blocks.member',
            'blocks.customMember',
        ]);
    });

    Route::post('/reporter/slot/{slot}/audio-chunk', function (Request $request, Slot $slot, AuditLogger $audit) {
        authorizeReporterAudio($request, $slot);

        $validated = $request->validate([
            'seq' => ['required', 'integer', 'min:1'],
            'chunk' => ['required_unless:mock,true', 'file', 'max:512000', 'mimetypes:audio/webm,video/webm,audio/wav,audio/x-wav,audio/mpeg,audio/mp3,audio/mpeg3,audio/mp4,video/mp4,audio/ogg'],
            'mock' => ['nullable', 'boolean'],
        ]);

        if (filter_var(env('REPORTER_AUDIO_MOCK', false), FILTER_VALIDATE_BOOL) || ($validated['mock'] ?? false)) {
            return response()->json([
                'slot_id' => $slot->id,
                'seq' => (int) $validated['seq'],
                'mock' => true,
            ]);
        }

        $seq = (int) $validated['seq'];
        $stored = app(ReporterAudioStorage::class)->putChunk($slot, $seq, $request->file('chunk'));

        $audit->log('reporter.audio.chunk.uploaded', $slot, [
            'slot_id' => $slot->id,
            'seq' => $seq,
            'path' => $stored['path'],
            'uri' => $stored['uri'],
            'bytes' => $stored['bytes'],
            'mime_type' => $stored['mime_type'] ?? null,
            'original_name' => $stored['original_name'] ?? null,
            'storage_provider' => 'minio',
        ]);

        return response()->json([
            'slot_id' => $slot->id,
            'seq' => $seq,
            'path' => $stored['path'],
            'uri' => $stored['uri'],
            'mime_type' => $stored['mime_type'] ?? null,
            'original_name' => $stored['original_name'] ?? null,
            'storage_provider' => 'minio',
        ]);
    });

    Route::post('/reporter/slot/{slot}/audio-close', function (Request $request, Slot $slot, AuditLogger $audit) {
        authorizeReporterAudio($request, $slot);

        return finalizeReporterAudio($slot, $audit);
    });

    Route::post('/reporter/slot/{slot}/recovery', [ReporterSlotController::class, 'recovery']);
    Route::post('/reporter/slot/{slot}/audit-sweep', [ReporterSlotController::class, 'auditSweep']);
    Route::get('/reporter/slot/{slot}/unification-preview', [ReporterSlotController::class, 'unificationPreview']);
    Route::patch('/reporter/slot/{slot}/duration', [ReporterSlotController::class, 'finaliseDuration']);
    Route::post('/slot-assignments/{assignment}/reassign', [ReporterSlotController::class, 'reassign']);

    Route::put('/blocks/{block}', function (BlockUpdateRequest $request, Block $block, AuditLogger $audit) {
        Gate::authorize('update', $block);

        return DB::transaction(function () use ($request, $block, $audit) {
            /** @var Block $locked */
            $locked = Block::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();

            if ((int) $request->validated('version') !== $locked->version) {
                return response()->json([
                    'current_version' => $locked->version,
                    'current_text' => $locked->text,
                ], 409);
            }

            $slot = $locked->slot()->lockForUpdate()->firstOrFail();
            $old = $locked->text;
            $new = $request->validated('text');

            $locked->forceFill([
                'text' => $new,
                'version' => $locked->version + 1,
                'reporter_edit_count' => $locked->reporter_edit_count + 1,
            ])->save();

            if ($slot->status === 'open') {
                $slot->forceFill(['status' => 'in_progress'])->save();
            }

            $audit->log('capture.block.edit', $locked, [
                'slot_code' => $slot->code,
                'before_excerpt' => mb_substr($old, 0, 120),
                'after_excerpt' => mb_substr($new, 0, 120),
                'char_delta' => mb_strlen($new) - mb_strlen($old),
                'lang' => $locked->original_lang,
            ]);

            return $locked->fresh(['member', 'customMember']);
        });
    });

    Route::put('/blocks/{block}/speaker', function (SpeakerUpdateRequest $request, Block $block, AuditLogger $audit) {
        Gate::authorize('update', $block);

        return DB::transaction(function () use ($request, $block, $audit) {
            /** @var Block $locked */
            $locked = Block::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $before = speakerSnapshot($locked);

            $locked->forceFill([
                'member_id' => $request->validated('member_id'),
                'custom_member_id' => $request->validated('custom_member_id'),
            ])->save();

            $after = speakerSnapshot($locked->fresh(['member', 'customMember']));
            $audit->log('capture.block.speaker', $locked, ['before' => $before, 'after' => $after]);

            return $locked->fresh(['member', 'customMember']);
        });
    });

    Route::post('/blocks/{block}/custom-members', function (CustomMemberRequest $request, Block $block, AuditLogger $audit) {
        Gate::authorize('update', $block);

        return DB::transaction(function () use ($request, $block, $audit) {
            /** @var Block $locked */
            $locked = Block::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $custom = MemberCustom::query()->create([
                ...$request->validated(),
                'slot_id' => $locked->slot_id,
                'created_by_user_id' => $request->user()->id,
            ]);

            $locked->forceFill([
                'member_id' => null,
                'custom_member_id' => $custom->id,
            ])->save();

            $audit->log('capture.block.custom_member', $locked, [
                'name_en' => $custom->name_en,
                'name_hi' => $custom->name_hi,
            ]);

            return response()->json($custom, 201);
        });
    });

    Route::post('/slots/{slot}/commit', function (SlotCommitRequest $request, Slot $slot, AuditLogger $audit) {
        $role = $request->validated('lang_role');
        Gate::authorize('commit', [$slot, $role]);

        return DB::transaction(function () use ($request, $slot, $role, $audit) {
            /** @var Slot $lockedSlot */
            $lockedSlot = Slot::query()->whereKey($slot->id)->lockForUpdate()->firstOrFail();
            /** @var SlotAssignment $assignment */
            $assignment = SlotAssignment::query()
                ->where('slot_id', $lockedSlot->id)
                ->where('user_id', $request->user()->id)
                ->where('lang_role', $role)
                ->lockForUpdate()
                ->firstOrFail();

            $blocks = $lockedSlot->blocks();
            $auditLog = $audit->log('capture.slot.commit', $lockedSlot, [
                'slot_code' => $lockedSlot->code,
                'lang_role' => $role,
                'block_count' => (clone $blocks)->where('original_lang', $role)->count(),
                'edit_count' => (clone $blocks)->where('original_lang', $role)->sum('reporter_edit_count'),
                'custom_member_count' => (clone $blocks)->where('original_lang', $role)->whereNotNull('custom_member_id')->count(),
            ]);
            $priorWorkflowStage = $assignment->workflow_stage;
            $workflowActionAt = now();

            $assignment->forceFill([
                'status' => 'committed',
                'workflow_stage' => 'supervisor',
                'assignee_user_id' => null,
                'committed_at' => $workflowActionAt,
                'committed_audit_log_id' => $auditLog->id,
                'last_workflow_action_at' => $workflowActionAt,
            ])->save();

            SlotWorkflowEvent::query()->create([
                'slot_assignment_id' => $assignment->id,
                'from_stage' => $priorWorkflowStage ?: 'reporter',
                'to_stage' => 'supervisor',
                'action' => 'commit',
                'actor_id' => $request->user()->id,
                'actor_role' => primaryRole($request->user()),
                'audit_log_id' => $auditLog->id,
                'created_at' => $workflowActionAt,
            ]);

            $remaining = $lockedSlot->assignments()->where('status', '!=', 'committed')->count();
            $lockedSlot->forceFill([
                'status' => $remaining === 0 ? 'committed_full' : 'committed_partial',
            ])->save();

            if (! filter_var(env('REPORTER_AUDIO_MOCK', false), FILTER_VALIDATE_BOOL)) {
                try {
                    finalizeReporterAudio($lockedSlot, $audit);
                } catch (\Throwable $exception) {
                    $audit->log('reporter.audio.finalize.skipped', $lockedSlot, [
                        'slot_id' => $lockedSlot->id,
                        'reason' => $exception->getMessage(),
                    ]);
                }
            }

            return $assignment->fresh(['slot']);
        });
    });

    Route::get('/members', function (MemberIndexRequest $request) {
        return Member::query()
            ->when($request->validated('category'), fn ($query, $category) => $query->where('category', $category))
            ->when($request->validated('q'), function ($query, $q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('name_en', 'ilike', $like)
                        ->orWhere('name_hi', 'ilike', $like)
                        ->orWhere('roster_id', 'ilike', $like)
                        ->orWhere('party', 'ilike', $like);
                });
            })
            ->orderBy('roster_id')
            ->paginate(50);
    });

    Route::get('/supervisor/queue', function (SupervisorQueueRequest $request) {
        /** @var User $user */
        $user = $request->user();
        $languages = $user->language_competencies ?? [];
        $requestedLang = $request->validated('lang');

        if ($requestedLang) {
            $languages = in_array($requestedLang, $languages, true) ? [$requestedLang] : [];
        }

        return SlotAssignment::query()
            ->where('workflow_stage', 'supervisor')
            ->whereIn('lang_role', $languages)
            ->when($request->validated('section'), fn ($query, $section) => $query->whereHas('user', fn ($userQuery) => $userQuery->where('section', $section)))
            ->with(['slot.sitting', 'user:id,name,employee_id,section'])
            ->orderBy('last_workflow_action_at')
            ->paginate(25)
            ->through(fn (SlotAssignment $assignment) => [
                'id' => $assignment->id,
                'lang_role' => $assignment->lang_role,
                'status' => $assignment->status,
                'workflow_stage' => $assignment->workflow_stage,
                'last_workflow_action_at' => $assignment->last_workflow_action_at,
                'reporter' => $assignment->user,
                'slot' => [
                    ...$assignment->slot->toArray(),
                    'sitting' => $assignment->slot->sitting,
                    'block_count' => $assignment->slot->blocks()->count(),
                    'edit_count' => $assignment->slot->blocks()->where('original_lang', $assignment->lang_role)->sum('reporter_edit_count'),
                ],
            ]);
    });

    Route::get('/slot-assignments/{assignment}', function (SlotAssignment $assignment) {
        Gate::authorize('view', $assignment);

        return assignmentDetail($assignment);
    });

    Route::get('/slot-assignments/{assignment}/history', function (SlotAssignment $assignment) {
        Gate::authorize('view', $assignment);

        return $assignment->workflowEvents()
            ->with(['actor:id,name,employee_id', 'auditLog:id,action,this_hash,created_at'])
            ->get()
            ->map(fn (SlotWorkflowEvent $event) => workflowEventPayload($event));
    });

    Route::post('/slot-assignments/{assignment}/forward', function (
        WorkflowForwardRequest $request,
        SlotAssignment $assignment,
        AuditLogger $audit
    ) {
        Gate::authorize('view', $assignment);

        return DB::transaction(function () use ($request, $assignment, $audit) {
            /** @var SlotAssignment $locked */
            $locked = SlotAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->workflow_stage !== 'supervisor') {
                return response()->json(['current_stage' => $locked->workflow_stage], 422);
            }

            Gate::authorize('forward', $locked);

            $slot = $locked->slot()->firstOrFail();
            $note = $request->validated('note');
            $auditLog = $audit->log('capture.workflow.forward', $locked, [
                'slot_code' => $slot->code,
                'lang_role' => $locked->lang_role,
                'reporter_id' => $locked->user_id,
                'note' => $note,
            ]);

            $locked->forceFill([
                'workflow_stage' => 'chief',
                'assignee_user_id' => null,
                'last_workflow_action_at' => now(),
            ])->save();

            SlotWorkflowEvent::query()->create([
                'slot_assignment_id' => $locked->id,
                'from_stage' => 'supervisor',
                'to_stage' => 'chief',
                'action' => 'forward',
                'actor_id' => $request->user()->id,
                'actor_role' => primaryRole($request->user()),
                'reason' => $note,
                'audit_log_id' => $auditLog->id,
                'created_at' => now(),
            ]);

            return assignmentDetail($locked);
        });
    });

    Route::post('/slot-assignments/{assignment}/return', function (
        WorkflowReturnRequest $request,
        SlotAssignment $assignment,
        AuditLogger $audit
    ) {
        Gate::authorize('view', $assignment);

        return DB::transaction(function () use ($request, $assignment, $audit) {
            /** @var SlotAssignment $locked */
            $locked = SlotAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->workflow_stage !== 'supervisor') {
                return response()->json(['current_stage' => $locked->workflow_stage], 422);
            }

            Gate::authorize('return', $locked);

            $slot = $locked->slot()->firstOrFail();
            $reason = $request->validated('reason');
            $auditLog = $audit->log('workflow.return', $locked, [
                'slot_code' => $slot->code,
                'lang_role' => $locked->lang_role,
                'reason' => $reason,
                'reporter_id' => $locked->user_id,
            ]);

            $locked->forceFill([
                'workflow_stage' => 'returned',
                'status' => 'in_progress',
                'committed_at' => null,
                'committed_audit_log_id' => null,
                'last_workflow_action_at' => now(),
            ])->save();

            SlotWorkflowEvent::query()->create([
                'slot_assignment_id' => $locked->id,
                'from_stage' => 'supervisor',
                'to_stage' => 'reporter',
                'action' => 'return',
                'actor_id' => $request->user()->id,
                'actor_role' => primaryRole($request->user()),
                'reason' => $reason,
                'audit_log_id' => $auditLog->id,
                'created_at' => now(),
            ]);

            return assignmentDetail($locked);
        });
    });
});

if (! function_exists('speakerSnapshot')) {
    function speakerSnapshot(Block $block): array
    {
        $block->loadMissing(['member', 'customMember']);

        return [
            'member_id' => $block->member_id,
            'custom_member_id' => $block->custom_member_id,
            'name_en' => $block->member?->name_en ?? $block->customMember?->name_en,
            'name_hi' => $block->member?->name_hi ?? $block->customMember?->name_hi,
        ];
    }
}

if (! function_exists('assignmentDetail')) {
    function assignmentDetail(SlotAssignment $assignment): array
    {
        $assignment->load([
            'user:id,name,employee_id,section,designation',
            'slot.sitting',
            'slot.blocks.member',
            'slot.blocks.customMember',
            'workflowEvents.actor:id,name,employee_id',
            'workflowEvents.auditLog:id,action,this_hash,created_at',
        ]);

        return [
            'assignment' => [
                ...$assignment->toArray(),
                'reporter' => $assignment->user,
                'slot' => [
                    ...$assignment->slot->toArray(),
                    'sitting' => $assignment->slot->sitting,
                    'blocks' => $assignment->slot->blocks,
                ],
                'history' => $assignment->workflowEvents->map(fn (SlotWorkflowEvent $event) => workflowEventPayload($event))->values(),
            ],
        ];
    }
}

if (! function_exists('workflowEventPayload')) {
    function workflowEventPayload(?SlotWorkflowEvent $event): ?array
    {
        if (! $event) {
            return null;
        }

        return [
            'id' => $event->id,
            'from_stage' => $event->from_stage,
            'to_stage' => $event->to_stage,
            'action' => $event->action,
            'actor_id' => $event->actor_id,
            'actor_role' => $event->actor_role,
            'actor' => $event->relationLoaded('actor') ? $event->actor : null,
            'reason' => $event->reason,
            'audit_log_id' => $event->audit_log_id,
            'audit_log' => $event->relationLoaded('auditLog') ? $event->auditLog : null,
            'created_at' => $event->created_at,
        ];
    }
}

if (! function_exists('primaryRole')) {
    function primaryRole(User $user): string
    {
        return $user->roles()->value('name') ?? 'authenticated';
    }
}

if (! function_exists('authorizeReporterAudio')) {
    function authorizeReporterAudio(Request $request, Slot $slot): void
    {
        abort_unless(
            $slot->assignments()
                ->where('user_id', $request->user()->id)
                ->where('status', '!=', 'committed')
                ->whereIn('workflow_stage', ['reporter', 'returned'])
                ->exists(),
            403
        );
    }
}

if (! function_exists('finalizeReporterAudio')) {
    function finalizeReporterAudio(Slot $slot, AuditLogger $audit)
    {
        if (filter_var(env('REPORTER_AUDIO_MOCK', false), FILTER_VALIDATE_BOOL)) {
            return response()->json(['slot_id' => $slot->id, 'mock' => true, 'closed' => true]);
        }

        $result = app(ReporterAudioFinalizer::class)->finalize($slot);

        return response()->json($result, ($result['closed'] ?? false) ? 200 : 422);
    }
}
