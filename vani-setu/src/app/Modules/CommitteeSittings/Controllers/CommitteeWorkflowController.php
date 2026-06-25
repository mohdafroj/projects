<?php

namespace App\Modules\CommitteeSittings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CommitteeSittings\Models\Committee;
use App\Modules\CommitteeSittings\Models\CommitteeDocument;
use App\Modules\CommitteeSittings\Models\CommitteeSitting;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Reports\Models\ReportSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommitteeWorkflowController extends Controller
{
    public function storeSitting(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'committee_code' => ['required', 'string', 'max:50'],
            'committee_name' => ['required', 'string', 'max:255'],
            'committee_type' => ['required', 'in:DRPSC,Joint,Select,Standing'],
            'meeting_no' => ['required', 'string', 'max:50'],
            'scheduled_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'terms_of_reference' => ['nullable', 'string'],
            'in_camera_default' => ['boolean'],
            'witnesses' => ['array'],
            'observers' => ['array'],
        ]);

        $sitting = DB::transaction(function () use ($validated, $audit) {
            $committee = Committee::query()->firstOrCreate(
                ['code' => $validated['committee_code']],
                [
                    'name' => $validated['committee_name'],
                    'type' => $validated['committee_type'],
                    'terms_of_reference' => $validated['terms_of_reference'] ?? null,
                ],
            );

            $houseSitting = Sitting::query()->create([
                'session_no' => 999,
                'sitting_no' => $validated['meeting_no'],
                'sitting_date' => substr($validated['scheduled_at'], 0, 10),
                'status' => 'planned',
                'started_at' => $validated['scheduled_at'],
            ]);

            $sitting = CommitteeSitting::query()->create([
                'committee_id' => $committee->id,
                'sitting_id' => $houseSitting->id,
                'meeting_no' => $validated['meeting_no'],
                'scheduled_at' => $validated['scheduled_at'],
                'venue' => $validated['venue'] ?? null,
                'in_camera_default' => $validated['in_camera_default'] ?? false,
                'witnesses' => $validated['witnesses'] ?? [],
                'observers' => $validated['observers'] ?? [],
            ]);

            $audit->log('committee.sitting.create', $sitting, [
                'committee_type' => $committee->type,
                'committee_code' => $committee->code,
                'in_camera_default' => $sitting->in_camera_default,
            ]);

            return $sitting->load('committee');
        });

        return response()->json(['sitting' => $sitting], 201);
    }

    public function commitSlot(Request $request, CommitteeSitting $committeeSitting, AuditLogger $audit)
    {
        $validated = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'text' => ['required', 'string'],
            'in_camera' => ['boolean'],
        ]);

        $block = Block::query()->findOrFail($validated['block_id']);
        $block->forceFill([
            'text' => $validated['text'],
            'source_type' => 'committee',
            'committee_id' => $committeeSitting->committee_id,
            'in_camera_flag' => $validated['in_camera'] ?? $committeeSitting->in_camera_default,
        ])->save();

        $audit->log('committee.capture.slot.commit', $block, [
            'committee_sitting_id' => $committeeSitting->id,
            'slot_id' => $validated['slot_id'],
            'in_camera' => (bool) $block->getAttribute('in_camera_flag'),
        ]);

        return response()->json(['block' => $block->fresh()]);
    }

    public function forward(Request $request, CommitteeSitting $committeeSitting, AuditLogger $audit)
    {
        $validated = $request->validate([
            'from_stage' => ['required', 'string', 'max:80'],
            'to_stage' => ['required', 'in:committee_chief,committee_secretariat,committee_chair,committee_reports'],
            'note' => ['nullable', 'string'],
        ]);

        $committeeSitting->forceFill(['status' => $validated['to_stage']])->save();
        $log = $audit->log('committee.workflow.forward', $committeeSitting, $validated);

        DB::table('committee_workflow_events')->insert([
            'committee_sitting_id' => $committeeSitting->id,
            'actor_id' => $request->user()?->id,
            'from_stage' => $validated['from_stage'],
            'to_stage' => $validated['to_stage'],
            'note' => $validated['note'] ?? null,
            'audit_log_id' => $log->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['sitting' => $committeeSitting->fresh()]);
    }

    public function chiefCommit(Request $request, CommitteeSitting $committeeSitting, AuditLogger $audit)
    {
        $document = $this->document($request, $committeeSitting, 'draft_committee_report', 'chief_consolidated');
        $audit->log('committee.chief.consolidation.commit', $document, [
            'committee_sitting_id' => $committeeSitting->id,
            'document_type' => $document->document_type,
        ]);

        return response()->json(['document' => $document->fresh()]);
    }

    public function secretariatReview(Request $request, CommitteeDocument $document, AuditLogger $audit)
    {
        $document->forceFill([
            'status' => 'secretariat_reviewed',
            'prepared_by_user_id' => $request->user()?->id,
        ])->save();

        $audit->log('committee.secretariat.review', $document, [
            'document_type' => $document->document_type,
            'in_camera' => $document->in_camera,
        ]);

        return response()->json(['document' => $document->fresh()]);
    }

    public function draftReport(Request $request, CommitteeDocument $document, AuditLogger $audit)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'document_type' => ['nullable', 'in:meeting_minutes,evidence_taken,draft_committee_report,final_committee_report,dissent_note'],
        ]);

        $document->forceFill([
            'title' => $validated['title'] ?? $document->title,
            'body' => $validated['body'] ?? $document->body,
            'document_type' => $validated['document_type'] ?? $document->document_type,
            'status' => 'drafted',
        ])->save();

        $audit->log('committee.report.draft', $document, [
            'document_type' => $document->document_type,
            'body_length' => mb_strlen((string) $document->body),
        ]);

        return response()->json(['document' => $document->fresh()]);
    }

    public function chairSign(CommitteeDocument $document, AuditLogger $audit)
    {
        $document->forceFill([
            'status' => 'chair_signed',
            'chair_signed_at' => now(),
            'dsc_serial' => sprintf('DSC-COM-STUB-%06d-%s', $document->id, now()->format('YmdHis')),
        ])->save();

        $audit->log('committee.chair.sign', $document, [
            'dsc_serial' => $document->dsc_serial,
            'document_type' => $document->document_type,
        ]);

        return response()->json(['document' => $document->fresh()]);
    }

    public function layReport(Request $request, CommitteeDocument $document, AuditLogger $audit)
    {
        $document->forceFill([
            'status' => 'laid_before_house',
            'laid_at' => now(),
            'prism_archive_ref' => 'PRISM-COM-'.$document->id.'-'.now()->format('YmdHis'),
        ])->save();

        $audit->log('committee.report.laid', $document, [
            'destination' => 'house_table_prism_archive',
            'prism_archive_ref' => $document->prism_archive_ref,
        ]);

        if ($document->in_camera) {
            $audit->log('in_camera.flag.applied', $document, [
                'committee_sitting_id' => $document->committee_sitting_id,
                'document_type' => $document->document_type,
                'reason' => 'Committee report retained in-camera restrictions through laying.',
            ]);
        }

        $snapshot = ReportSnapshot::query()->create([
            'name' => 'Committee report '.$document->id.' laid',
            'filters' => ['committee_document_id' => $document->id],
            'chart_data' => ['committee_reports_laid' => 1],
            'export_meta' => ['destination' => 'PRISM'],
            'captured_by_user_id' => $request->user()?->id,
            'captured_at' => now(),
        ]);

        $snapshotLog = $audit->log('reports.committee.snapshot.captured', $snapshot, [
            'committee_document_id' => $document->id,
            'committee_sitting_id' => $document->committee_sitting_id,
        ]);
        $snapshot->forceFill(['captured_audit_log_id' => $snapshotLog->id])->save();

        return response()->json(['document' => $document->fresh(), 'snapshot' => $snapshot->fresh()]);
    }

    private function document(Request $request, CommitteeSitting $committeeSitting, string $type, string $status): CommitteeDocument
    {
        $document = CommitteeDocument::query()->firstOrCreate(
            [
                'committee_sitting_id' => $committeeSitting->id,
                'document_type' => $type,
            ],
            [
                'prepared_by_user_id' => $request->user()?->id,
                'status' => $status,
                'title' => $committeeSitting->committee->name.' report '.$committeeSitting->meeting_no,
                'body' => 'Committee evidence, minutes, draft report, final report, and dissent notes are consolidated here.',
                'in_camera' => $committeeSitting->in_camera_default,
            ],
        );

        $document->forceFill(['status' => $status])->save();

        return $document;
    }
}
