<?php

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Js\Models\ExpungeCandidate;
use App\Modules\Js\Models\JsSgHandoff;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Sg\Models\SgManualExpunge;
use App\Modules\Sg\Models\SgReview;
use App\Modules\Sg\Services\DscAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('sg')->middleware(['auth:sanctum', 'role:sg'])->group(function () {
    Route::get('/tray', function () {
        return JsWindow::query()
            ->with('sitting')
            ->where('status', 'sent_to_sg')
            ->orderBy('sitting_id')
            ->orderBy('starts_at_offset_ms')
            ->get()
            ->map(fn (JsWindow $window) => sg_window_summary($window));
    });

    Route::get('/windows/{window}', fn (JsWindow $window) => ['window' => sg_window_detail($window)]);

    Route::post('/windows/{window}/open', function (Request $request, JsWindow $window, AuditLogger $audit) {
        sg_abort_unless_sent($window);

        return DB::transaction(function () use ($request, $window, $audit) {
            /** @var JsWindow $locked */
            $locked = JsWindow::query()->whereKey($window->id)->lockForUpdate()->firstOrFail();
            sg_abort_unless_sent($locked);

            $auditLog = $audit->log('sg.window.open', $locked, [
                'window_id' => $locked->id,
                'pending_expunges' => $locked->expungeCandidatesCount('pending'),
            ]);

            $review = SgReview::query()->updateOrCreate(
                ['window_id' => $locked->id],
                [
                    'sg_user_id' => $request->user()->id,
                    'opened_at' => now(),
                    'audit_log_id_open' => $auditLog->id,
                    ...sg_review_counts($locked),
                ],
            );

            return ['review' => $review->fresh(), 'window' => sg_window_detail($locked->fresh())];
        });
    });

    Route::post('/windows/{window}/expunges/{ec}/confirm', function (
        Request $request,
        JsWindow $window,
        ExpungeCandidate $ec,
        AuditLogger $audit
    ) {
        return sg_decide_expunge($request, $window, $ec, 'confirmed', 'sg.expunge.confirm', $audit);
    });

    Route::post('/windows/{window}/expunges/{ec}/override', function (
        Request $request,
        JsWindow $window,
        ExpungeCandidate $ec,
        AuditLogger $audit
    ) {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return sg_decide_expunge($request, $window, $ec, 'overridden', 'sg.expunge.override', $audit, $validated);
    });

    Route::post('/windows/{window}/manual-expunges', function (Request $request, JsWindow $window, AuditLogger $audit) {
        sg_abort_unless_sent($window);
        $validated = $request->validate([
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'word' => ['required', 'string', 'max:255'],
            'grounds' => ['required', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($request, $window, $validated, $audit) {
            /** @var JsWindow $locked */
            $locked = JsWindow::query()->whereKey($window->id)->lockForUpdate()->firstOrFail();
            sg_abort_unless_sent($locked);

            /** @var Block $block */
            $block = Block::query()->whereKey($validated['block_id'])->firstOrFail();
            sg_abort_unless_block_in_window($locked, $block);

            $auditLog = $audit->log('sg.manual_expunge.add', $block, [
                'window_id' => $locked->id,
                'block_id' => $block->id,
                'word' => $validated['word'],
                'grounds' => $validated['grounds'],
            ]);

            $manual = SgManualExpunge::query()->create([
                'window_id' => $locked->id,
                'block_id' => $block->id,
                'word' => $validated['word'],
                'grounds' => $validated['grounds'],
                'added_by_sg_user_id' => $request->user()->id,
                'audit_log_id' => $auditLog->id,
            ]);

            sg_sync_review_counts($locked, $request->user()->id);

            return ['manual_expunge' => $manual->fresh(['block.member', 'block.customMember', 'auditLog'])];
        });
    });

    Route::post('/windows/{window}/sign', function (Request $request, JsWindow $window, DscAdapter $dsc, AuditLogger $audit) {
        sg_abort_unless_sent($window);

        return DB::transaction(function () use ($request, $window, $dsc, $audit) {
            /** @var JsWindow $locked */
            $locked = JsWindow::query()->whereKey($window->id)->lockForUpdate()->firstOrFail();
            sg_abort_unless_sent($locked);

            $signature = $dsc->sign($locked);
            $auditLog = $audit->log('sg.window.sign', $locked, [
                'window_id' => $locked->id,
                'dsc_serial' => $signature['serial'],
                ...sg_review_counts($locked),
            ]);

            $review = SgReview::query()->updateOrCreate(
                ['window_id' => $locked->id],
                [
                    'sg_user_id' => $request->user()->id,
                    'opened_at' => SgReview::query()->where('window_id', $locked->id)->value('opened_at') ?? now(),
                    'signed_at' => $signature['signed_at'],
                    'dsc_serial' => $signature['serial'],
                    'audit_log_id_sign' => $auditLog->id,
                    ...sg_review_counts($locked),
                ],
            );

            JsSgHandoff::query()
                ->where('window_id', $locked->id)
                ->whereNull('returned_at')
                ->latest()
                ->first()?->forceFill([
                    'returned_at' => $signature['signed_at'],
                    'returned_audit_log_id' => $auditLog->id,
                    'dsc_serial' => $signature['serial'],
                    'sg_user_id' => $request->user()->id,
                    'confirmed_expunges' => $review->confirmed_expunges,
                    'manual_expunges' => $review->manual_expunges,
                ])->save();

            $locked->forceFill(['status' => 'sg_returned'])->save();

            return ['review' => $review->fresh(), 'window' => sg_window_detail($locked->fresh())];
        });
    });

    Route::get('/windows/{window}/history', function (JsWindow $window) {
        return [
            'review' => SgReview::query()
                ->where('window_id', $window->id)
                ->with(['sgUser:id,name,employee_id', 'openAuditLog:id,action,this_hash,created_at', 'signAuditLog:id,action,this_hash,created_at'])
                ->first(),
            'manual_expunges' => SgManualExpunge::query()
                ->where('window_id', $window->id)
                ->with(['sgUser:id,name,employee_id', 'block', 'auditLog:id,action,this_hash,created_at'])
                ->latest()
                ->get(),
            'audit' => AuditLog::query()
                ->whereIn('action', ['sg.window.open', 'sg.expunge.confirm', 'sg.expunge.override', 'sg.manual_expunge.add', 'sg.window.sign'])
                ->where(fn ($query) => $query
                    ->where('subject_id', (string) $window->id)
                    ->orWhere('payload->window_id', $window->id))
                ->latest('id')
                ->get(),
        ];
    });
});

if (! function_exists('sg_window_summary')) {
function sg_window_summary(JsWindow $window): array
{
    return [
        ...$window->only(['id', 'sitting_id', 'window_code', 'starts_at_offset_ms', 'duration_ms', 'status', 'created_at', 'updated_at']),
        'sitting' => $window->relationLoaded('sitting') ? $window->sitting : null,
        'block_count' => $window->blocks()->count(),
        'expunge_candidates_count' => $window->expungeCandidatesCount(),
        'pending_expunge_candidates_count' => $window->expungeCandidatesCount('pending'),
        'confirmed_expunges_count' => $window->expungeCandidatesCount('confirmed'),
        'overridden_expunges_count' => $window->expungeCandidatesCount('overridden'),
        'manual_expunges_count' => SgManualExpunge::query()->where('window_id', $window->id)->count(),
        'review' => SgReview::query()->where('window_id', $window->id)->first(),
    ];
}
}

if (! function_exists('sg_window_detail')) {
function sg_window_detail(JsWindow $window): array
{
    $window->load('sitting');

    return [
        ...sg_window_summary($window),
        'blocks' => $window->blocks()->get(),
        'expunge_candidates' => $window->expungeCandidates()->with(['block.member', 'block.customMember'])->orderBy('id')->get(),
        'manual_expunges' => SgManualExpunge::query()->where('window_id', $window->id)->with(['block.member', 'block.customMember'])->orderBy('id')->get(),
        'handoffs' => $window->handoffs()->with(['sgUser:id,name,employee_id'])->get(),
    ];
}
}

if (! function_exists('sg_decide_expunge')) {
function sg_decide_expunge(Request $request, JsWindow $window, ExpungeCandidate $candidate, string $state, string $action, AuditLogger $audit, array $payload = [])
{
    sg_abort_unless_sent($window);
    sg_abort_unless_child($window, $candidate);

    return DB::transaction(function () use ($request, $window, $candidate, $state, $action, $audit, $payload) {
        /** @var JsWindow $lockedWindow */
        $lockedWindow = JsWindow::query()->whereKey($window->id)->lockForUpdate()->firstOrFail();
        sg_abort_unless_sent($lockedWindow);

        /** @var ExpungeCandidate $locked */
        $locked = ExpungeCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
        sg_abort_unless_child($lockedWindow, $locked);
        $locked->forceFill(['state' => $state])->save();

        $auditLog = $audit->log($action, $locked, [
            'window_id' => $lockedWindow->id,
            'block_id' => $locked->block_id,
            'word' => $locked->word,
            'state' => $state,
            ...$payload,
        ]);

        sg_sync_review_counts($lockedWindow, $request->user()->id);

        return ['candidate' => $locked->fresh(['block.member', 'block.customMember']), 'audit_log_id' => $auditLog->id];
    });
}
}

if (! function_exists('sg_sync_review_counts')) {
function sg_sync_review_counts(JsWindow $window, int $sgUserId): SgReview
{
    $existing = SgReview::query()->where('window_id', $window->id)->first();

    return SgReview::query()->updateOrCreate(
        ['window_id' => $window->id],
        [
            'sg_user_id' => $existing?->sg_user_id ?? $sgUserId,
            'opened_at' => $existing?->opened_at ?? now(),
            'audit_log_id_open' => $existing?->audit_log_id_open,
            ...sg_review_counts($window),
        ],
    );
}
}

if (! function_exists('sg_review_counts')) {
function sg_review_counts(JsWindow $window): array
{
    return [
        'confirmed_expunges' => $window->expungeCandidatesCount('confirmed'),
        'overridden_expunges' => $window->expungeCandidatesCount('overridden'),
        'manual_expunges' => SgManualExpunge::query()->where('window_id', $window->id)->count(),
    ];
}
}

if (! function_exists('sg_abort_unless_sent')) {
function sg_abort_unless_sent(JsWindow $window): void
{
    if ($window->status !== 'sent_to_sg') {
        abort(response()->json(['message' => 'Window must be sent to SG.'], 422));
    }
}
}

if (! function_exists('sg_abort_unless_child')) {
function sg_abort_unless_child(JsWindow $window, ExpungeCandidate $candidate): void
{
    if ((int) $candidate->window_id !== (int) $window->id) {
        abort(404);
    }
}
}

if (! function_exists('sg_abort_unless_block_in_window')) {
function sg_abort_unless_block_in_window(JsWindow $window, Block $block): void
{
    if (! $window->blocks()->where('blocks.id', $block->id)->exists()) {
        abort(404);
    }
}
}
