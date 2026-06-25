<?php

namespace App\Modules\SgDash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\SlotWorkflowEvent;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Js\Models\JsSgHandoff;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Sg\Models\SgReview;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SgDashController extends Controller
{
    private const STATUSES = ['open', 'under_review', 'sent_to_sg', 'sg_returned', 'approved', 'published_handoff'];

    public function dates(Request $request, AuditLogger $audit)
    {
        $dates = Sitting::query()
            ->select('sitting_date')
            ->withCount('slots')
            ->whereHas('slots')
            ->orderByDesc('sitting_date')
            ->get()
            ->map(fn (Sitting $sitting) => [
                'date' => $sitting->sitting_date?->toDateString(),
                'slots_count' => $sitting->slots_count,
                'windows_count' => JsWindow::query()->where('sitting_id', $sitting->id)->count(),
            ]);

        $audit->log('sg_dash.date_switcher', null, [
            'available_dates' => $dates->pluck('date')->filter()->values()->all(),
        ]);

        return ['dates' => $dates];
    }

    public function pipeline(Request $request, AuditLogger $audit)
    {
        $date = $this->date($request);
        $windows = $this->windowsForDate($date)->with(['sitting', 'handoffs', 'expungeCandidates'])->get();
        $statusCounts = $windows->countBy('status');

        $payload = [
            'date' => $date,
            'summary' => [
                'windows_total' => $windows->count(),
                'sent_to_sg' => (int) ($statusCounts['sent_to_sg'] ?? 0),
                'returned_from_sg' => (int) ($statusCounts['sg_returned'] ?? 0),
                'pending_expunges' => $windows->sum(fn (JsWindow $window) => $window->expungeCandidates->where('state', 'pending')->count()),
                'confirmed_expunges' => $windows->sum(fn (JsWindow $window) => $window->expungeCandidates->where('state', 'confirmed')->count()),
                'manual_expunges' => DB::table('sg_manual_expunges')
                    ->join('js_windows', 'sg_manual_expunges.window_id', '=', 'js_windows.id')
                    ->join('sittings', 'js_windows.sitting_id', '=', 'sittings.id')
                    ->whereDate('sittings.sitting_date', $date)
                    ->count(),
            ],
            'statuses' => collect(self::STATUSES)->map(fn (string $status) => [
                'status' => $status,
                'count' => (int) ($statusCounts[$status] ?? 0),
            ])->values(),
        ];

        $audit->log('sg_dash.pipeline', null, [
            'date' => $date,
            'windows_total' => $payload['summary']['windows_total'],
        ]);

        return $payload;
    }

    public function ageing(Request $request, AuditLogger $audit)
    {
        $date = $this->date($request);
        $now = CarbonImmutable::now();
        $windows = $this->windowsForDate($date)
            ->with(['sitting', 'handoffs' => fn ($query) => $query->latest()])
            ->where('status', 'sent_to_sg')
            ->orderBy('starts_at_offset_ms')
            ->get()
            ->map(function (JsWindow $window) use ($now) {
                $handoff = $window->handoffs->first();
                $sentAt = $handoff?->sent_at ? CarbonImmutable::parse($handoff->sent_at) : CarbonImmutable::parse($window->updated_at);
                $ageMinutes = max(0, (int) $sentAt->diffInMinutes($now));

                return [
                    'window' => $this->windowSummary($window),
                    'sent_at' => $sentAt->toIso8601String(),
                    'age_minutes' => $ageMinutes,
                    'bucket' => $this->ageBucket($ageMinutes),
                ];
            });

        $buckets = collect(['0_30', '31_60', '61_120', 'over_120'])->map(fn (string $bucket) => [
            'bucket' => $bucket,
            'count' => $windows->where('bucket', $bucket)->count(),
        ])->values();

        $audit->log('sg_dash.ageing', null, [
            'date' => $date,
            'pending_windows' => $windows->count(),
        ]);

        return ['date' => $date, 'buckets' => $buckets, 'windows' => $windows->values()];
    }

    public function feed(Request $request, AuditLogger $audit)
    {
        $date = $this->date($request);
        $windowIds = $this->windowsForDate($date)->pluck('id');

        $handoffs = JsSgHandoff::query()
            ->with('window.sitting')
            ->whereIn('window_id', $windowIds)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (JsSgHandoff $handoff) => [
                'kind' => 'handoff',
                'at' => ($handoff->returned_at ?? $handoff->sent_at)?->toIso8601String(),
                'window_id' => $handoff->window_id,
                'title' => $handoff->returned_at ? 'SG returned signed window' : 'JS sent window to SG',
                'meta' => [
                    'window_code' => $handoff->window?->window_code,
                    'dsc_serial' => $handoff->dsc_serial,
                    'confirmed_expunges' => $handoff->confirmed_expunges,
                    'manual_expunges' => $handoff->manual_expunges,
                ],
            ]);

        $reviews = SgReview::query()
            ->with('window.sitting')
            ->whereIn('window_id', $windowIds)
            ->latest('id')
            ->limit(20)
            ->get()
            ->flatMap(function (SgReview $review) {
                return collect([
                    $review->opened_at ? [
                        'kind' => 'review',
                        'at' => $review->opened_at->toIso8601String(),
                        'window_id' => $review->window_id,
                        'title' => 'SG opened review',
                        'meta' => ['window_code' => $review->window?->window_code],
                    ] : null,
                    $review->signed_at ? [
                        'kind' => 'review',
                        'at' => $review->signed_at->toIso8601String(),
                        'window_id' => $review->window_id,
                        'title' => 'SG signed decision',
                        'meta' => [
                            'window_code' => $review->window?->window_code,
                            'dsc_serial' => $review->dsc_serial,
                        ],
                    ] : null,
                ])->filter();
            });

        $workflow = SlotWorkflowEvent::query()
            ->with('actor:id,name,employee_id')
            ->whereHas('slotAssignment.slot.sitting', fn (Builder $query) => $query->whereDate('sitting_date', $date))
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (SlotWorkflowEvent $event) => [
                'kind' => 'workflow',
                'at' => $event->created_at?->toIso8601String(),
                'window_id' => null,
                'title' => "{$event->from_stage} {$event->action} {$event->to_stage}",
                'meta' => [
                    'actor_role' => $event->actor_role,
                    'actor' => $event->actor?->name,
                    'reason' => $event->reason,
                ],
            ]);

        $auditItems = AuditLog::query()
            ->where(fn (Builder $query) => $query
                ->where('action', 'like', 'sg.%')
                ->orWhere('action', 'like', 'js.window.forward_sg'))
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn (AuditLog $log) => $windowIds->contains((int) ($log->payload['window_id'] ?? 0)))
            ->take(20)
            ->map(fn (AuditLog $log) => [
                'kind' => 'audit',
                'at' => $log->created_at?->toIso8601String(),
                'window_id' => isset($log->payload['window_id']) ? (int) $log->payload['window_id'] : null,
                'title' => $log->action,
                'meta' => ['hash' => $log->this_hash],
            ]);

        $items = $handoffs
            ->concat($reviews)
            ->concat($workflow)
            ->concat($auditItems)
            ->filter(fn (array $item) => filled($item['at']))
            ->sortByDesc('at')
            ->values()
            ->take(40);

        $audit->log('sg_dash.feed', null, [
            'date' => $date,
            'items' => $items->count(),
        ]);

        return ['date' => $date, 'items' => $items];
    }

    public function windows(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);
        $date = $validated['date'] ?? $this->defaultDate();

        $windows = $this->windowsForDate($date)
            ->with(['sitting', 'handoffs', 'expungeCandidates'])
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('starts_at_offset_ms')
            ->get()
            ->map(fn (JsWindow $window) => $this->windowSummary($window));

        $audit->log('sg_dash.drilldown', null, [
            'date' => $date,
            'status' => $validated['status'] ?? null,
            'windows' => $windows->count(),
        ]);

        return ['date' => $date, 'windows' => $windows];
    }

    public function show(Request $request, JsWindow $window, AuditLogger $audit)
    {
        $window->load(['sitting', 'handoffs.sgUser:id,name,employee_id', 'expungeCandidates.block', 'suggestedEdits.block']);

        $audit->log('sg_dash.window', $window, [
            'window_id' => $window->id,
            'status' => $window->status,
        ]);

        return [
            'window' => [
                ...$this->windowSummary($window),
                'handoffs' => $window->handoffs,
                'review' => SgReview::query()->where('window_id', $window->id)->with('sgUser:id,name,employee_id')->first(),
                'expunge_candidates' => $window->expungeCandidates,
                'suggested_edits' => $window->suggestedEdits,
                'audit' => AuditLog::query()
                    ->where(fn (Builder $query) => $query
                        ->where('subject_id', (string) $window->id)
                        ->orWhere('payload->window_id', $window->id))
                    ->where(fn (Builder $query) => $query
                        ->where('action', 'like', 'sg.%')
                        ->orWhere('action', 'like', 'js.%'))
                    ->latest('id')
                    ->limit(30)
                    ->get(),
            ],
        ];
    }

    private function date(Request $request): string
    {
        $validated = $request->validate(['date' => ['nullable', 'date']]);

        return $validated['date'] ?? $this->defaultDate();
    }

    private function defaultDate(): string
    {
        return Sitting::query()->max('sitting_date') ?? now()->toDateString();
    }

    private function windowsForDate(string $date): Builder
    {
        return JsWindow::query()
            ->whereHas('sitting', fn (Builder $query) => $query->whereDate('sitting_date', $date));
    }

    private function windowSummary(JsWindow $window): array
    {
        $expunges = $window->relationLoaded('expungeCandidates') ? $window->expungeCandidates : $window->expungeCandidates()->get();
        $handoffs = $window->relationLoaded('handoffs') ? $window->handoffs : $window->handoffs()->get();

        return [
            'id' => $window->id,
            'sitting_id' => $window->sitting_id,
            'window_code' => $window->window_code,
            'starts_at_offset_ms' => $window->starts_at_offset_ms,
            'duration_ms' => $window->duration_ms,
            'status' => $window->status,
            'sitting' => $window->relationLoaded('sitting') ? $window->sitting : null,
            'pending_expunges' => $expunges->where('state', 'pending')->count(),
            'confirmed_expunges' => $expunges->where('state', 'confirmed')->count(),
            'overridden_expunges' => $expunges->where('state', 'overridden')->count(),
            'handoff_count' => $handoffs->count(),
            'last_sent_at' => $handoffs->sortByDesc('sent_at')->first()?->sent_at?->toIso8601String(),
            'last_returned_at' => $handoffs->whereNotNull('returned_at')->sortByDesc('returned_at')->first()?->returned_at?->toIso8601String(),
        ];
    }

    private function ageBucket(int $minutes): string
    {
        return match (true) {
            $minutes <= 30 => '0_30',
            $minutes <= 60 => '31_60',
            $minutes <= 120 => '61_120',
            default => 'over_120',
        };
    }
}
