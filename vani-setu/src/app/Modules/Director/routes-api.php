<?php

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Director\Jobs\RunDirectorPublishJob;
use App\Modules\Director\Models\DirectorPublishJob;
use App\Modules\Js\Models\JsWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('director')->middleware(['auth:sanctum', 'role:director'])->group(function () {
    Route::get('/inbox', function (Request $request, AuditLogger $audit) {
        director_sync_approved_windows($request, $audit);

        return DirectorPublishJob::query()
            ->with(['window.sitting', 'director:id,name,employee_id'])
            ->whereHas('window', fn ($query) => $query->where('status', 'approved'))
            ->orderBy('queued_at')
            ->get()
            ->map(fn (DirectorPublishJob $job) => director_job_detail($job));
    });

    Route::get('/jobs', fn () => DirectorPublishJob::query()
        ->with(['window.sitting', 'director:id,name,employee_id'])
        ->latest('queued_at')
        ->latest('id')
        ->get()
        ->map(fn (DirectorPublishJob $job) => director_job_detail($job)));

    Route::get('/jobs/{job}', fn (DirectorPublishJob $job) => ['job' => director_job_detail($job->load(['window.sitting', 'director']))]);

    Route::post('/jobs/{job}/publish', function (Request $request, DirectorPublishJob $job, AuditLogger $audit) {
        return DB::transaction(function () use ($request, $job, $audit) {
            /** @var DirectorPublishJob $locked */
            $locked = DirectorPublishJob::query()->with('window')->whereKey($job->id)->lockForUpdate()->firstOrFail();

            if ($locked->window->status !== 'approved') {
                return response()->json(['message' => 'Window must be approved before Director publish.'], 422);
            }

            if (! in_array($locked->status, ['queued', 'failed'], true)) {
                return response()->json(['message' => 'Publish job has already started.'], 409);
            }

            $retryingFailedJob = $locked->status === 'failed';

            $locked->forceFill([
                'director_user_id' => $request->user()->id,
                'queued_at' => now(),
                'status' => 'queued',
                'last_error' => null,
                'finished_at' => null,
            ])->save();

            if ($retryingFailedJob) {
                $audit->log('director.job.queued', $locked, ['window_id' => $locked->window_id, 'director_user_id' => $request->user()->id]);
            }

            RunDirectorPublishJob::dispatch($locked->id);

            return ['job' => director_job_detail($locked->fresh(['window.sitting', 'director']))];
        });
    });

    Route::get('/jobs/{job}/log', function (DirectorPublishJob $job) {
        return [
            'job' => director_job_detail($job->load(['window.sitting', 'director'])),
            'audit' => AuditLog::query()
                ->whereIn('action', ['director.job.queued', 'director.crc.generated', 'director.sansad.pushed', 'director.job.failed'])
                ->where(fn ($query) => $query
                    ->where('subject_id', (string) $job->id)
                    ->orWhere('payload->window_id', $job->window_id))
                ->latest('id')
                ->get(),
        ];
    });
});

if (! function_exists('director_sync_approved_windows')) {
function director_sync_approved_windows(Request $request, AuditLogger $audit): void
{
    JsWindow::query()
        ->where('status', 'approved')
        ->whereNotIn('id', DirectorPublishJob::query()->select('window_id'))
        ->orderBy('id')
        ->each(function (JsWindow $window) use ($request, $audit) {
            $job = DirectorPublishJob::query()->create([
                'window_id' => $window->id,
                'director_user_id' => $request->user()->id,
                'queued_at' => now(),
                'status' => 'queued',
            ]);
            $audit->log('director.job.queued', $job, ['window_id' => $window->id, 'director_user_id' => $request->user()->id]);
        });
}
}

if (! function_exists('director_job_detail')) {
function director_job_detail(DirectorPublishJob $job): array
{
    $window = $job->window;

    return [
        ...$job->only(['id', 'window_id', 'director_user_id', 'queued_at', 'ran_at', 'finished_at', 'status', 'crc_pdf_path', 'sansad_url', 'last_error', 'created_at', 'updated_at']),
        'window' => $window ? [
            ...$window->only(['id', 'sitting_id', 'window_code', 'starts_at_offset_ms', 'duration_ms', 'status']),
            'sitting' => $window->relationLoaded('sitting') ? $window->sitting : null,
            'block_count' => $window->blocks()->count(),
        ] : null,
        'director' => $job->relationLoaded('director') ? $job->director : null,
    ];
}
}
