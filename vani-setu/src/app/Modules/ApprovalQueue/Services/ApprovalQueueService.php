<?php

namespace App\Modules\ApprovalQueue\Services;

use App\Modules\ApprovalQueue\Models\ApprovalQueueAction;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use App\Modules\Director\Models\DirectorPublishJob;
use App\Modules\Formatting\Models\FormattingJob;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Sg\Models\SgReview;
use App\Modules\Translator\Models\TranslatorAssignment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ApprovalQueueService
{
    /**
     * @param  array{module?: string, priority?: string, include_acknowledged?: bool, include_snoozed?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(User $user, array $filters = []): Collection
    {
        $items = collect()
            ->merge($this->slotAssignmentItems($user))
            ->merge($this->translatorItems($user))
            ->merge($this->jsItems($user))
            ->merge($this->sgItems($user))
            ->merge($this->directorItems($user))
            ->merge($this->formattingItems($user));

        $actions = ApprovalQueueAction::query()
            ->where('user_id', $user->id)
            ->whereIn('item_key', $items->pluck('key')->all())
            ->get()
            ->keyBy('item_key');

        $items = $items->map(function (array $item) use ($actions) {
            /** @var ApprovalQueueAction|null $action */
            $action = $actions->get($item['key']);

            return [
                ...$item,
                'queue_action' => $action ? [
                    'action' => $action->action,
                    'note' => $action->note,
                    'snoozed_until' => $action->snoozed_until,
                    'audit_log_id' => $action->audit_log_id,
                ] : null,
            ];
        });

        if (! ($filters['include_acknowledged'] ?? false)) {
            $items = $items->reject(fn (array $item) => ($item['queue_action']['action'] ?? null) === 'acknowledged');
        }

        if (! ($filters['include_snoozed'] ?? false)) {
            $items = $items->reject(function (array $item): bool {
                if (($item['queue_action']['action'] ?? null) !== 'snoozed') {
                    return false;
                }

                $until = $item['queue_action']['snoozed_until'] ?? null;

                return $until instanceof Carbon && $until->isFuture();
            });
        }

        return $items
            ->when($filters['module'] ?? null, fn (Collection $queue, string $module) => $queue->where('module', $module))
            ->when($filters['priority'] ?? null, fn (Collection $queue, string $priority) => $queue->where('priority', $priority))
            ->sortBy([
                fn (array $a, array $b) => $this->priorityRank($a['priority']) <=> $this->priorityRank($b['priority']),
                fn (array $a, array $b) => strcmp((string) $a['due_at'], (string) $b['due_at']),
                fn (array $a, array $b) => strcmp($a['key'], $b['key']),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function summary(Collection $items): array
    {
        return [
            'total' => $items->count(),
            'by_module' => $items->countBy('module')->all(),
            'by_priority' => $items->countBy('priority')->all(),
            'oldest_due_at' => $items->min('due_at'),
        ];
    }

    public function findForUser(User $user, string $itemKey): ?array
    {
        return $this->forUser($user, ['include_acknowledged' => true, 'include_snoozed' => true])
            ->firstWhere('key', $itemKey);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function slotAssignmentItems(User $user): Collection
    {
        if (! $user->isAdmin() && ! $user->isReporter() && ! $user->isSupervisor() && ! $user->isChief()) {
            return collect();
        }

        /** @var EloquentCollection<int, SlotAssignment> $assignments */
        $assignments = SlotAssignment::query()
            ->with(['slot.sitting', 'user:id,name,employee_id', 'assignee:id,name,employee_id'])
            ->whereIn('workflow_stage', ['reporter', 'returned', 'supervisor', 'chief'])
            ->orderBy('last_workflow_action_at')
            ->orderBy('id')
            ->limit(250)
            ->get()
            ->filter(fn (SlotAssignment $assignment) => $this->slotAssignmentVisibleTo($user, $assignment));

        return $assignments->map(fn (SlotAssignment $assignment) => [
            'key' => "capture.slot_assignment.{$assignment->id}",
            'module' => 'capture',
            'type' => 'slot_assignment',
            'subject_id' => $assignment->id,
            'title' => trim(($assignment->slot?->code ?? "Slot {$assignment->slot_id}").' '.$assignment->lang_role),
            'description' => $this->stageDescription($assignment->workflow_stage),
            'status' => $assignment->workflow_stage,
            'priority' => $assignment->workflow_stage === 'returned' ? 'high' : 'normal',
            'due_at' => optional($assignment->last_workflow_action_at ?? $assignment->updated_at)->toIso8601String(),
            'action_label' => $this->slotActionLabel($assignment->workflow_stage),
            'route' => $assignment->workflow_stage === 'reporter' || $assignment->workflow_stage === 'returned'
                ? "/capture/slots/{$assignment->slot_id}"
                : "/workflow-board",
            'meta' => [
                'slot_id' => $assignment->slot_id,
                'sitting_id' => $assignment->slot?->sitting_id,
                'sitting_date' => $assignment->slot?->sitting?->sitting_date,
                'lang_role' => $assignment->lang_role,
                'reporter' => $assignment->user?->only(['id', 'name', 'employee_id']),
            ],
        ]);
    }

    private function slotAssignmentVisibleTo(User $user, SlotAssignment $assignment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return match ($assignment->workflow_stage) {
            'reporter', 'returned' => $assignment->user_id === $user->id,
            'supervisor' => $user->isSupervisor() && $this->hasLanguage($user, $assignment->lang_role),
            'chief' => $user->isChief(),
            default => false,
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function translatorItems(User $user): Collection
    {
        if (! $user->isAdmin() && ! $user->isTranslator()) {
            return collect();
        }

        return TranslatorAssignment::query()
            ->with(['slot.sitting', 'translator:id,name,employee_id'])
            ->whereIn('status', ['open', 'in_review', 'returned'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('translator_user_id', $user->id))
            ->orderBy('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (TranslatorAssignment $assignment) => [
                'key' => "translator.assignment.{$assignment->id}",
                'module' => 'translator',
                'type' => 'translator_assignment',
                'subject_id' => $assignment->id,
                'title' => "Translation {$assignment->language_pair}",
                'description' => "Translation assignment is {$assignment->status}.",
                'status' => $assignment->status,
                'priority' => $assignment->status === 'returned' ? 'high' : 'normal',
                'due_at' => optional($assignment->updated_at)->toIso8601String(),
                'action_label' => 'Open translation',
                'route' => "/translator/assignments/{$assignment->id}",
                'meta' => [
                    'slot_id' => $assignment->slot_id,
                    'sitting_id' => $assignment->sitting_id,
                    'sitting_date' => $assignment->slot?->sitting?->sitting_date,
                    'translator' => $assignment->translator?->only(['id', 'name', 'employee_id']),
                ],
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function jsItems(User $user): Collection
    {
        if (! $user->isAdmin() && ! $user->isJs()) {
            return collect();
        }

        return JsWindow::query()
            ->with('sitting')
            ->whereIn('status', ['open', 'under_review', 'sg_returned'])
            ->orderBy('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (JsWindow $window) => [
                'key' => "js.window.{$window->id}",
                'module' => 'js',
                'type' => 'js_window',
                'subject_id' => $window->id,
                'title' => "JS window {$window->window_code}",
                'description' => "Window has {$window->suggestedEditsCount('pending')} edits and {$window->expungeCandidatesCount('pending')} expunges pending.",
                'status' => $window->status,
                'priority' => $window->status === 'sg_returned' ? 'high' : 'normal',
                'due_at' => optional($window->updated_at)->toIso8601String(),
                'action_label' => 'Review window',
                'route' => "/js/windows/{$window->id}",
                'meta' => [
                    'sitting_id' => $window->sitting_id,
                    'sitting_date' => $window->sitting?->sitting_date,
                    'pending_suggested_edits' => $window->suggestedEditsCount('pending'),
                    'pending_expunges' => $window->expungeCandidatesCount('pending'),
                ],
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sgItems(User $user): Collection
    {
        if (! $user->isAdmin() && ! $user->isSg()) {
            return collect();
        }

        return JsWindow::query()
            ->with('sitting')
            ->where('status', 'sent_to_sg')
            ->whereDoesntHave('handoffs', fn ($query) => $query->whereNotNull('returned_at'))
            ->orderBy('updated_at')
            ->limit(100)
            ->get()
            ->map(function (JsWindow $window) {
                $review = SgReview::query()->where('window_id', $window->id)->latest('id')->first();

                return [
                    'key' => "sg.window.{$window->id}",
                    'module' => 'sg',
                    'type' => 'sg_window',
                    'subject_id' => $window->id,
                    'title' => "SG review {$window->window_code}",
                    'description' => "DSC review has {$window->expungeCandidatesCount('pending')} pending expunges.",
                    'status' => $review?->opened_at ? 'opened' : 'pending_signature',
                    'priority' => 'high',
                    'due_at' => optional($window->updated_at)->toIso8601String(),
                    'action_label' => 'Open SG tray',
                    'route' => "/sg/windows/{$window->id}",
                    'meta' => [
                        'sitting_id' => $window->sitting_id,
                        'sitting_date' => $window->sitting?->sitting_date,
                        'pending_expunges' => $window->expungeCandidatesCount('pending'),
                        'opened_at' => $review?->opened_at,
                    ],
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function directorItems(User $user): Collection
    {
        if (! $user->isAdmin() && ! $user->isChief()) {
            return collect();
        }

        return DirectorPublishJob::query()
            ->with('window.sitting')
            ->whereIn('status', ['queued', 'failed'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('director_user_id', $user->id))
            ->orderBy('queued_at')
            ->limit(100)
            ->get()
            ->map(fn (DirectorPublishJob $job) => [
                'key' => "director.job.{$job->id}",
                'module' => 'director',
                'type' => 'director_publish_job',
                'subject_id' => $job->id,
                'title' => "Publish {$job->window?->window_code}",
                'description' => $job->status === 'failed' ? "Publish failed: {$job->last_error}" : 'Publish job is queued.',
                'status' => $job->status,
                'priority' => $job->status === 'failed' ? 'high' : 'normal',
                'due_at' => optional($job->queued_at ?? $job->updated_at)->toIso8601String(),
                'action_label' => 'Open director job',
                'route' => "/director/jobs/{$job->id}",
                'meta' => [
                    'window_id' => $job->window_id,
                    'sitting_id' => $job->window?->sitting_id,
                    'sitting_date' => $job->window?->sitting?->sitting_date,
                ],
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function formattingItems(User $user): Collection
    {
        if (! $user->isAdmin() && ! $user->can('formatting.queue')) {
            return collect();
        }

        return FormattingJob::query()
            ->with('window.sitting')
            ->whereIn('status', ['draft', 'validated', 'crc_ready'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('formatter_user_id', $user->id))
            ->orderBy('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (FormattingJob $job) => [
                'key' => "formatting.job.{$job->id}",
                'module' => 'formatting',
                'type' => 'formatting_job',
                'subject_id' => $job->id,
                'title' => strtoupper($job->artifact_type).' formatting',
                'description' => "Formatting job is {$job->status}.",
                'status' => $job->status,
                'priority' => $job->status === 'crc_ready' ? 'high' : 'normal',
                'due_at' => optional($job->updated_at)->toIso8601String(),
                'action_label' => 'Open formatting job',
                'route' => "/formatting/jobs/{$job->id}",
                'meta' => [
                    'window_id' => $job->window_id,
                    'sitting_id' => $job->sitting_id,
                    'sitting_date' => $job->window?->sitting?->sitting_date,
                ],
            ]);
    }

    private function stageDescription(string $stage): string
    {
        return match ($stage) {
            'reporter' => 'Reporter capture/edit is pending.',
            'returned' => 'Assignment was returned and needs revision.',
            'supervisor' => 'Supervisor approval is pending.',
            'chief' => 'Chief consolidation approval is pending.',
            default => 'Approval is pending.',
        };
    }

    private function slotActionLabel(string $stage): string
    {
        return match ($stage) {
            'reporter', 'returned' => 'Open capture slot',
            'supervisor' => 'Review supervisor queue',
            'chief' => 'Open chief queue',
            default => 'Open item',
        };
    }

    private function hasLanguage(User $user, string $langRole): bool
    {
        return in_array($langRole, $user->language_competencies ?? [], true);
    }

    private function priorityRank(string $priority): int
    {
        return ['high' => 0, 'normal' => 1, 'low' => 2][$priority] ?? 3;
    }
}
