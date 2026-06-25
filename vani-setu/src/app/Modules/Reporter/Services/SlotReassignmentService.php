<?php

namespace App\Modules\Reporter\Services;

use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\SlotWorkflowEvent;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SlotReassignmentService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function reassign(SlotAssignment $assignment, User $newReporter, User $supervisor, string $reason): SlotAssignment
    {
        if (! $newReporter->hasRole('reporter')) {
            throw ValidationException::withMessages([
                'user_id' => ['Selected user is not a Reporter.'],
            ]);
        }

        if (! in_array($assignment->lang_role, $newReporter->language_competencies ?? [], true)) {
            throw ValidationException::withMessages([
                'user_id' => ['Selected Reporter does not have the required language competency.'],
            ]);
        }

        return DB::transaction(function () use ($assignment, $newReporter, $supervisor, $reason) {
            /** @var SlotAssignment $locked */
            $locked = SlotAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $previousUserId = $locked->user_id;
            $previousStage = $locked->workflow_stage;

            $auditLog = $this->audit->log('reporter.slot.reassigned', $locked, [
                'slot_id' => $locked->slot_id,
                'lang_role' => $locked->lang_role,
                'from_user_id' => $previousUserId,
                'to_user_id' => $newReporter->id,
                'supervisor_id' => $supervisor->id,
                'reason' => $reason,
            ]);

            $locked->forceFill([
                'user_id' => $newReporter->id,
                'assignee_user_id' => null,
                'status' => 'open',
                'workflow_stage' => 'reporter',
                'committed_at' => null,
                'committed_audit_log_id' => null,
                'last_workflow_action_at' => $auditLog->created_at,
            ])->save();

            SlotWorkflowEvent::query()->create([
                'slot_assignment_id' => $locked->id,
                'from_stage' => $previousStage ?: 'reporter',
                'to_stage' => 'reporter',
                'action' => 'return',
                'actor_id' => $supervisor->id,
                'actor_role' => $supervisor->roles()->value('name') ?? 'authenticated',
                'reason' => $reason,
                'audit_log_id' => $auditLog->id,
                'created_at' => $auditLog->created_at,
            ]);

            $slot = $locked->slot()->lockForUpdate()->firstOrFail();
            $committedCount = $slot->assignments()->where('status', 'committed')->count();
            $slot->forceFill([
                'status' => $committedCount > 0 ? 'committed_partial' : 'in_progress',
            ])->save();

            return $locked->fresh(['slot', 'user']);
        });
    }
}
