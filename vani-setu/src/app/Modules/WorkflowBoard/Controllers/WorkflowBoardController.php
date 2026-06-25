<?php

namespace App\Modules\WorkflowBoard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\SlotWorkflowEvent;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkflowBoardController extends Controller
{
    private const STAGES = ['reporter', 'returned', 'supervisor', 'chief'];

    /**
     * @return array{stages: array<string, array<int, array<string, mixed>>>, counts: array<string, int>}
     */
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'stage' => ['nullable', Rule::in(self::STAGES)],
            'lang' => ['nullable', 'string', 'max:16'],
            'sitting_id' => ['nullable', 'integer', 'exists:sittings,id'],
        ]);

        $assignments = SlotAssignment::query()
            ->with([
                'user:id,name,employee_id,section,designation',
                'assignee:id,name,employee_id',
                'slot.sitting',
                'workflowEvents.actor:id,name,employee_id',
            ])
            ->when($validated['stage'] ?? null, fn ($query, $stage) => $query->where('workflow_stage', $stage))
            ->when($validated['lang'] ?? null, fn ($query, $lang) => $query->where('lang_role', $lang))
            ->when($validated['sitting_id'] ?? null, fn ($query, $sittingId) => $query->whereHas('slot', fn ($slot) => $slot->where('sitting_id', $sittingId)))
            ->whereHas('slot.sitting', fn ($query) => $query->whereIn('status', ['live', 'planned']))
            ->orderBy('last_workflow_action_at')
            ->orderBy('id')
            ->limit(200)
            ->get();

        $stages = array_fill_keys(self::STAGES, []);
        foreach ($assignments as $assignment) {
            $stages[$assignment->workflow_stage][] = $this->assignmentPayload($assignment);
        }

        return [
            'stages' => $stages,
            'counts' => collect($stages)->map(fn (array $items) => count($items))->all(),
        ];
    }

    /**
     * @return array{assignment: array<string, mixed>, allowed_transitions: array<int, string>}
     */
    public function show(Request $request, SlotAssignment $assignment): array
    {
        $assignment->load([
            'user:id,name,employee_id,section,designation',
            'assignee:id,name,employee_id',
            'slot.sitting',
            'slot.blocks',
            'workflowEvents.actor:id,name,employee_id',
            'workflowEvents.auditLog:id,action,this_hash,created_at',
        ]);

        return [
            'assignment' => $this->assignmentPayload($assignment, true),
            'allowed_transitions' => array_values(array_keys($this->allowedTargets($request->user(), $assignment))),
        ];
    }

    /**
     * @return array{assignment: array<string, mixed>, audit_log_id: int}
     */
    public function transition(Request $request, SlotAssignment $assignment, AuditLogger $audit): array|JsonResponse
    {
        $validated = $request->validate([
            'to_stage' => ['required', Rule::in(self::STAGES)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $assignment, $validated, $audit) {
            /** @var SlotAssignment $locked */
            $locked = SlotAssignment::query()
                ->with(['slot.sitting'])
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allowedTargets = $this->allowedTargets($request->user(), $locked);
            $toStage = $validated['to_stage'];

            if (! array_key_exists($toStage, $allowedTargets)) {
                return response()->json([
                    'message' => 'Transition is not allowed for this role and stage.',
                    'current_stage' => $locked->workflow_stage,
                    'allowed_transitions' => array_values(array_keys($allowedTargets)),
                ], 403);
            }

            $fromStage = $locked->workflow_stage;
            $action = $allowedTargets[$toStage];
            $at = now();
            $reason = $validated['reason'] ?? null;

            $auditLog = $audit->log('workflow_board.'.$action, $locked, [
                'slot_code' => $locked->slot?->code,
                'sitting_id' => $locked->slot?->sitting_id,
                'lang_role' => $locked->lang_role,
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
                'reason' => $reason,
            ]);

            $locked->forceFill([
                'workflow_stage' => $toStage,
                'status' => $toStage === 'reporter' || $toStage === 'returned' ? 'in_progress' : $locked->status,
                'committed_at' => $toStage === 'reporter' || $toStage === 'returned' ? null : $locked->committed_at,
                'committed_audit_log_id' => $toStage === 'reporter' || $toStage === 'returned' ? null : $locked->committed_audit_log_id,
                'assignee_user_id' => null,
                'last_workflow_action_at' => $at,
            ])->save();

            SlotWorkflowEvent::query()->create([
                'slot_assignment_id' => $locked->id,
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
                'action' => $action,
                'actor_id' => $request->user()->id,
                'actor_role' => $this->primaryRole($request->user()),
                'reason' => $reason,
                'audit_log_id' => $auditLog->id,
                'created_at' => $at,
            ]);

            return [
                'assignment' => $this->assignmentPayload($locked->fresh(), true),
                'audit_log_id' => $auditLog->id,
            ];
        });
    }

    /**
     * @return array<string, string>
     */
    private function allowedTargets(User $user, SlotAssignment $assignment): array
    {
        if ($user->isAdmin()) {
            return collect(self::STAGES)
                ->reject(fn (string $stage) => $stage === $assignment->workflow_stage)
                ->mapWithKeys(fn (string $stage) => [$stage => $this->actionFor($assignment->workflow_stage, $stage)])
                ->all();
        }

        return match ($assignment->workflow_stage) {
            'supervisor' => $user->isSupervisor() && $this->hasLanguage($user, $assignment->lang_role)
                ? ['chief' => 'forward', 'returned' => 'return']
                : [],
            'chief' => $user->isChief()
                ? ['supervisor' => 'return', 'reporter' => 'return']
                : [],
            default => [],
        };
    }

    private function actionFor(string $fromStage, string $toStage): string
    {
        $rank = ['reporter' => 0, 'returned' => 0, 'supervisor' => 1, 'chief' => 2];

        return ($rank[$toStage] ?? 0) > ($rank[$fromStage] ?? 0) ? 'forward' : 'return';
    }

    private function hasLanguage(User $user, string $langRole): bool
    {
        return in_array($langRole, $user->language_competencies ?? [], true);
    }

    private function primaryRole(User $user): string
    {
        return $user->roles()->value('name') ?? 'authenticated';
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentPayload(SlotAssignment $assignment, bool $includeHistory = false): array
    {
        $assignment->loadMissing(['user:id,name,employee_id,section,designation', 'assignee:id,name,employee_id', 'slot.sitting']);

        $payload = [
            'id' => $assignment->id,
            'slot_id' => $assignment->slot_id,
            'user_id' => $assignment->user_id,
            'assignee_user_id' => $assignment->assignee_user_id,
            'lang_role' => $assignment->lang_role,
            'status' => $assignment->status,
            'workflow_stage' => $assignment->workflow_stage,
            'committed_at' => $assignment->committed_at,
            'last_workflow_action_at' => $assignment->last_workflow_action_at,
            'reporter' => $assignment->user,
            'assignee' => $assignment->assignee,
            'slot' => [
                ...$assignment->slot->toArray(),
                'sitting' => $assignment->slot->sitting,
                'block_count' => $assignment->slot->blocks()->count(),
                'edit_count' => $assignment->slot->blocks()->where('original_lang', $assignment->lang_role)->sum('reporter_edit_count'),
            ],
            'latest_event' => $assignment->workflowEvents->first() ? $this->eventPayload($assignment->workflowEvents->first()) : null,
        ];

        if ($includeHistory) {
            $payload['history'] = $assignment->workflowEvents->map(fn (SlotWorkflowEvent $event) => $this->eventPayload($event))->values();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(SlotWorkflowEvent $event): array
    {
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
