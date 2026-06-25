<?php

namespace App\Modules\Formatting\Controllers;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Formatting\Models\FormattingJob;
use App\Modules\Formatting\Models\FormattingTransition;
use App\Modules\Formatting\Services\FormattingAssembler;
use App\Modules\Js\Models\JsWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormattingController
{
    public function queue()
    {
        return FormattingJob::query()
            ->with(['window.sitting', 'sitting', 'formatter'])
            ->latest()
            ->get();
    }

    public function create(Request $request, FormattingAssembler $assembler, AuditLogger $audit)
    {
        $validated = $request->validate([
            'window_id' => ['required', 'integer', 'exists:js_windows,id'],
            'artifact_type' => ['required', 'in:fv,hv,ev,synopsis'],
        ]);

        return DB::transaction(function () use ($validated, $request, $assembler, $audit) {
            /** @var JsWindow $window */
            $window = JsWindow::query()->with('sitting')->lockForUpdate()->findOrFail($validated['window_id']);

            if ($window->status !== 'approved') {
                return response()->json(['message' => 'Formatting requires an approved JS/SG window.'], 422);
            }

            $assembled = $assembler->assemble($window, $validated['artifact_type'], $request->user());
            $job = FormattingJob::query()->create([
                'window_id' => $window->id,
                'sitting_id' => $window->sitting_id,
                'formatter_user_id' => $request->user()->id,
                'artifact_type' => $validated['artifact_type'],
                'status' => 'draft',
                'metadata' => $assembled['metadata'],
                'crc_source_hash' => $assembled['hash'],
                'page_count' => $assembled['page_count'],
            ]);

            foreach ($assembled['lines'] as $line) {
                $job->lines()->create($line);
            }

            $auditLog = $audit->log('formatting.job.created', $job, [
                'window_id' => $window->id,
                'artifact_type' => $job->artifact_type,
                'crc_source_hash' => $job->crc_source_hash,
                'line_count' => count($assembled['lines']),
            ]);
            $job->forceFill(['created_audit_log_id' => $auditLog->id])->save();
            $this->transition($job, $request, 'create', null, 'draft', $auditLog);

            return response()->json($this->detail($job->fresh()), 201);
        });
    }

    public function show(FormattingJob $job): array
    {
        return $this->detail($job);
    }

    public function validateJob(Request $request, FormattingJob $job, FormattingAssembler $assembler, AuditLogger $audit)
    {
        return DB::transaction(function () use ($request, $job, $assembler, $audit) {
            /** @var FormattingJob $locked */
            $locked = FormattingJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'draft') {
                return response()->json(['message' => 'Only draft formatting jobs can be validated.'], 422);
            }

            $report = $assembler->validate($locked);
            $auditLog = $audit->log('formatting.policy.checked', $locked, [
                'ok' => $report['ok'],
                'errors' => $report['errors'],
                'warnings' => $report['warnings'],
            ]);

            $locked->forceFill([
                'policy_report' => $report,
                'validated_audit_log_id' => $auditLog->id,
            ]);

            if ($report['ok']) {
                $locked->status = 'validated';
            }

            $locked->save();
            $this->transition($locked, $request, 'validate', 'draft', $locked->status, $auditLog, $report);

            return $report['ok']
                ? $this->detail($locked->fresh())
                : response()->json(['job' => $this->detail($locked->fresh()), 'policy_report' => $report], 422);
        });
    }

    public function crc(Request $request, FormattingJob $job, FormattingAssembler $assembler, AuditLogger $audit)
    {
        return DB::transaction(function () use ($request, $job, $assembler, $audit) {
            /** @var FormattingJob $locked */
            $locked = FormattingJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'validated') {
                return response()->json(['message' => 'CRC can be generated only after policy validation.'], 422);
            }

            $path = $assembler->renderCrc($locked);
            $auditLog = $audit->log('formatting.crc.compiled', $locked, [
                'crc_path' => $path,
                'crc_source_hash' => $locked->crc_source_hash,
                'page_count' => $locked->page_count,
            ]);

            $locked->forceFill([
                'status' => 'crc_ready',
                'crc_path' => $path,
                'crc_audit_log_id' => $auditLog->id,
            ])->save();
            $this->transition($locked, $request, 'crc', 'validated', 'crc_ready', $auditLog);

            return $this->detail($locked->fresh());
        });
    }

    public function dispatch(Request $request, FormattingJob $job, AuditLogger $audit)
    {
        return DB::transaction(function () use ($request, $job, $audit) {
            /** @var FormattingJob $locked */
            $locked = FormattingJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'crc_ready') {
                return response()->json(['message' => 'Only CRC-ready jobs can be dispatched.'], 422);
            }

            $auditLog = $audit->log('formatting.dispatch', $locked, [
                'crc_path' => $locked->crc_path,
                'artifact_type' => $locked->artifact_type,
            ]);

            $locked->forceFill([
                'status' => 'dispatched',
                'dispatched_audit_log_id' => $auditLog->id,
            ])->save();
            $this->transition($locked, $request, 'dispatch', 'crc_ready', 'dispatched', $auditLog);

            return $this->detail($locked->fresh());
        });
    }

    public function audit(FormattingJob $job): array
    {
        return [
            'job' => $job->load(['window', 'sitting']),
            'transitions' => $job->transitions()->with('auditLog')->get(),
            'audit' => AuditLog::query()
                ->where('subject_type', $job->getMorphClass())
                ->where('subject_id', (string) $job->id)
                ->orderBy('id')
                ->get(['id', 'action', 'payload', 'this_hash', 'created_at']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(FormattingJob $job): array
    {
        $job->load(['window.sitting', 'sitting', 'formatter', 'lines', 'transitions']);

        return [
            'job' => $job,
            'lines' => $job->lines,
            'transitions' => $job->transitions,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function transition(FormattingJob $job, Request $request, string $action, ?string $from, string $to, $auditLog, array $payload = []): void
    {
        FormattingTransition::query()->create([
            'job_id' => $job->id,
            'actor_id' => $request->user()->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'audit_log_id' => $auditLog->id,
            'payload' => $payload,
        ]);
    }
}
