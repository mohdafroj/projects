<?php

namespace App\Modules\MasterDash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Director\Models\DirectorPublishJob;
use App\Modules\Js\Models\JsWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MasterDashController extends Controller
{
    public function overview(Request $request, AuditLogger $audit): array
    {
        $audit->log('master_dash.overview.view', null, [
            'user_id' => $request->user()->id,
            'roles' => $request->user()->roles()->pluck('name')->values()->all(),
        ]);

        return [
            'generated_at' => now()->toISOString(),
            'actor' => $this->actor($request),
            'sittings' => $this->sittingsSummary(),
            'workflow' => $this->workflowSummary(),
            'pendency' => $this->pendencySummary(),
            'roster' => $this->rosterSummary(),
            'audit' => $this->auditSummary(),
        ];
    }

    public function pendency(Request $request, AuditLogger $audit): array
    {
        $audit->log('master_dash.pendency.view', null, [
            'user_id' => $request->user()->id,
            'stage' => $request->query('stage'),
        ]);

        $stage = $request->query('stage');
        $assignments = SlotAssignment::query()
            ->with(['slot.sitting', 'assignee:id,name,employee_id,section,designation', 'user:id,name,employee_id,section,designation'])
            ->when($stage, fn ($query) => $query->where('workflow_stage', $stage))
            ->whereIn('status', ['open', 'in_progress'])
            ->orderBy('workflow_stage')
            ->orderBy('last_workflow_action_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        return [
            'generated_at' => now()->toISOString(),
            'summary' => $this->pendencySummary(),
            'items' => $assignments->map(fn (SlotAssignment $assignment) => $this->pendencyItem($assignment))->values(),
        ];
    }

    public function roster(Request $request, AuditLogger $audit): array
    {
        $audit->log('master_dash.roster.view', null, ['user_id' => $request->user()->id]);

        return [
            'generated_at' => now()->toISOString(),
            'roles' => $this->rosterSummary(),
            'users' => User::query()
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id', 'email', 'section', 'designation', 'language_competencies', 'is_active', 'last_login_at'])
                ->map(fn (User $user) => [
                    ...$user->only(['id', 'name', 'employee_id', 'email', 'section', 'designation', 'language_competencies', 'is_active', 'last_login_at']),
                    'roles' => $user->roles->pluck('name')->values(),
                ])
                ->values(),
        ];
    }

    private function actor(Request $request): array
    {
        return [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'employee_id' => $request->user()->employee_id,
            'roles' => $request->user()->roles()->pluck('name')->values(),
        ];
    }

    private function sittingsSummary(): array
    {
        return [
            'total' => Sitting::query()->count(),
            'live' => Sitting::query()->where('status', 'live')->count(),
            'latest' => Sitting::query()
                ->withCount('slots')
                ->latest('sitting_date')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Sitting $sitting) => [
                    ...$sitting->only(['id', 'session_no', 'sitting_no', 'sitting_date', 'status', 'started_at', 'ended_at']),
                    'slots_count' => $sitting->slots_count,
                ])
                ->values(),
        ];
    }

    private function workflowSummary(): array
    {
        return [
            'slots_by_status' => $this->countsBy(Slot::query()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status')),
            'assignments_by_stage' => $this->countsBy(SlotAssignment::query()->select('workflow_stage', DB::raw('count(*) as aggregate'))->groupBy('workflow_stage')->pluck('aggregate', 'workflow_stage')),
            'assignments_by_status' => $this->countsBy(SlotAssignment::query()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status')),
            'chief_consolidations_by_status' => $this->countsBy(ChiefConsolidation::query()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status')),
            'js_windows_by_status' => $this->countsBy(JsWindow::query()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status')),
            'director_jobs_by_status' => $this->countsBy(DirectorPublishJob::query()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status')),
        ];
    }

    private function pendencySummary(): array
    {
        $pendingStatuses = ['open', 'in_progress'];

        return [
            'total' => SlotAssignment::query()->whereIn('status', $pendingStatuses)->count(),
            'by_stage' => $this->countsBy(SlotAssignment::query()
                ->whereIn('status', $pendingStatuses)
                ->select('workflow_stage', DB::raw('count(*) as aggregate'))
                ->groupBy('workflow_stage')
                ->pluck('aggregate', 'workflow_stage')),
            'oldest' => SlotAssignment::query()
                ->with(['slot.sitting', 'assignee:id,name,employee_id'])
                ->whereIn('status', $pendingStatuses)
                ->orderBy('last_workflow_action_at')
                ->orderBy('id')
                ->limit(10)
                ->get()
                ->map(fn (SlotAssignment $assignment) => $this->pendencyItem($assignment))
                ->values(),
        ];
    }

    private function rosterSummary(): array
    {
        return User::query()
            ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->select('roles.name', DB::raw('count(*) as total'), DB::raw("sum(case when users.is_active then 1 else 0 end) as active"))
            ->groupBy('roles.name')
            ->orderBy('roles.name')
            ->get()
            ->map(fn ($row) => [
                'role' => $row->name,
                'total' => (int) $row->total,
                'active' => (int) $row->active,
            ])
            ->values()
            ->all();
    }

    private function auditSummary(): array
    {
        return [
            'latest' => AuditLog::query()
                ->latest('id')
                ->limit(12)
                ->get(['id', 'action', 'actor_role', 'subject_type', 'subject_id', 'this_hash', 'created_at', 'payload']),
            'master_dash_events' => AuditLog::query()->where('action', 'like', 'master_dash.%')->count(),
        ];
    }

    private function pendencyItem(SlotAssignment $assignment): array
    {
        return [
            ...$assignment->only(['id', 'slot_id', 'user_id', 'assignee_user_id', 'lang_role', 'status', 'workflow_stage', 'committed_at', 'last_workflow_action_at']),
            'slot' => $assignment->slot ? [
                ...$assignment->slot->only(['id', 'sitting_id', 'code', 'start_offset_ms', 'duration_ms', 'topic', 'status']),
                'sitting' => $assignment->slot->sitting?->only(['id', 'session_no', 'sitting_no', 'sitting_date', 'status']),
            ] : null,
            'assignee' => $assignment->assignee,
            'user' => $assignment->user,
        ];
    }

    /**
     * @param  Collection<string, int|string>  $counts
     * @return array<string, int>
     */
    private function countsBy(Collection $counts): array
    {
        return $counts->mapWithKeys(fn ($count, $key) => [(string) $key => (int) $count])->all();
    }
}
