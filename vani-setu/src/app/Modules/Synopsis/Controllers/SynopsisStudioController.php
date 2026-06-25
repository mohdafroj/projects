<?php

namespace App\Modules\Synopsis\Controllers;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Synopsis\Models\SynopsisDocument;
use App\Modules\Synopsis\Models\SynopsisDocumentEdit;
use App\Modules\Synopsis\Requests\SynopsisDraftRequest;
use App\Modules\Synopsis\Services\SynopsisDraftService;
use App\Modules\Synopsis\Services\SynopsisPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SynopsisStudioController
{
    public function queue(Request $request)
    {
        $this->authorizeSynopsis($request->user());

        return ChiefConsolidation::query()
            ->with(['sitting', 'commits', 'edits'])
            ->whereIn('status', ['dual_committed', 'forwarded_to_js'])
            ->orderBy('sitting_id')
            ->orderBy('starts_at_offset_ms')
            ->get()
            ->map(fn (ChiefConsolidation $consolidation) => $this->summary($consolidation));
    }

    public function show(Request $request, ChiefConsolidation $consolidation): array
    {
        $this->authorizeSynopsis($request->user());

        return $this->detail($consolidation);
    }

    public function generate(
        Request $request,
        ChiefConsolidation $consolidation,
        SynopsisDraftService $drafts,
        AuditLogger $audit
    ) {
        $this->authorizeSynopsis($request->user());
        $this->ensureReady($consolidation);
        $document = $this->documentFor($consolidation, $request->user());
        $this->ensureDraftEditable($document);
        $draft = $drafts->generate($consolidation);

        return DB::transaction(function () use ($request, $consolidation, $audit, $document, $draft) {
            $consolidation->refresh();
            $this->ensureReady($consolidation);
            /** @var SynopsisDocument $document */
            $document = SynopsisDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $this->ensureDraftEditable($document);
            $this->ensureDraftSourceCurrent($draft, $consolidation);
            $before = $document->body;
            $fromVersion = $document->version;
            $auditLog = $audit->log('synopsis.draft.generated', $document, [
                'consolidation_id' => $consolidation->id,
                'chunk_code' => $consolidation->window_code,
                'from_version' => $fromVersion,
                'block_count' => $consolidation->blocks()->count(),
                'source_sha256' => $draft['source_sha256'] ?? null,
                'generation' => $draft['generation_meta'] ?? null,
            ]);

            $document->forceFill([
                'writer_user_id' => $request->user()->id,
                'source_mode' => 'ai',
                'status' => 'draft',
                'title' => $draft['title'],
                'body' => $draft['body'],
                'attributions' => $draft['attributions'],
                'ai_first_draft' => true,
                'version' => $fromVersion + 1,
                'last_audit_log_id' => $auditLog->id,
            ])->save();

            $this->recordEdit($document, $request->user(), 'generate', $fromVersion, $document->version, $before, $document->body, $auditLog->id);

            return $this->detail($consolidation->fresh());
        });
    }

    public function generateFromText(
        Request $request,
        ChiefConsolidation $consolidation,
        SynopsisDraftService $drafts,
        AuditLogger $audit
    ) {
        $this->authorizeSynopsis($request->user());
        $this->ensureReady($consolidation);
        $data = $request->validate([
            'source_text' => ['required', 'string', 'min:40', 'max:50000'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);
        $data['source_text'] = trim($data['source_text']);
        $data['title'] = isset($data['title']) ? trim((string) $data['title']) : null;

        if (mb_strlen($data['source_text']) < 40) {
            throw ValidationException::withMessages([
                'source_text' => ['The source text field must be at least 40 meaningful characters.'],
            ]);
        }

        $document = $this->documentFor($consolidation, $request->user());
        $this->ensureDraftEditable($document);
        $draft = $drafts->generateFromText($consolidation, $data['source_text'], $data['title'] ?? null);

        return DB::transaction(function () use ($request, $consolidation, $audit, $data, $document, $draft) {
            $consolidation->refresh();
            $this->ensureReady($consolidation);
            /** @var SynopsisDocument $document */
            $document = SynopsisDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $this->ensureDraftEditable($document);
            $before = $document->body;
            $fromVersion = $document->version;
            $auditLog = $audit->log('synopsis.draft.generated_from_text', $document, [
                'consolidation_id' => $consolidation->id,
                'chunk_code' => $consolidation->window_code,
                'from_version' => $fromVersion,
                'source_characters' => mb_strlen($data['source_text']),
                'source_sha256' => $draft['source_sha256'] ?? hash('sha256', $data['source_text']),
                'attribution_count' => count($draft['attributions']),
                'generation' => $draft['generation_meta'] ?? null,
            ]);

            $document->forceFill([
                'writer_user_id' => $request->user()->id,
                'source_mode' => 'ai',
                'status' => 'draft',
                'title' => $draft['title'],
                'body' => $draft['body'],
                'attributions' => $draft['attributions'],
                'ai_first_draft' => true,
                'version' => $fromVersion + 1,
                'last_audit_log_id' => $auditLog->id,
            ])->save();

            $this->recordEdit($document, $request->user(), 'generate', $fromVersion, $document->version, $before, $document->body, $auditLog->id);

            return $this->detail($consolidation->fresh());
        });
    }

    public function author(Request $request, ChiefConsolidation $consolidation, AuditLogger $audit)
    {
        $this->authorizeSynopsis($request->user());
        $this->ensureReady($consolidation);

        return DB::transaction(function () use ($request, $consolidation, $audit) {
            $consolidation->refresh();
            $this->ensureReady($consolidation);
            $document = $this->documentFor($consolidation, $request->user());
            $this->ensureDraftEditable($document);
            $before = $document->body;
            $fromVersion = $document->version;
            $auditLog = $audit->log('synopsis.draft.author', $document, [
                'consolidation_id' => $consolidation->id,
                'chunk_code' => $consolidation->window_code,
                'from_version' => $fromVersion,
            ]);

            $document->forceFill([
                'writer_user_id' => $request->user()->id,
                'source_mode' => 'scratch',
                'status' => 'draft',
                'title' => $document->title ?: "Synopsis - Chunk {$consolidation->window_code}",
                'body' => '',
                'attributions' => [],
                'ai_first_draft' => false,
                'version' => $fromVersion + 1,
                'last_audit_log_id' => $auditLog->id,
            ])->save();

            $this->recordEdit($document, $request->user(), 'author', $fromVersion, $document->version, $before, '', $auditLog->id);

            return $this->detail($consolidation->fresh());
        });
    }

    public function save(SynopsisDraftRequest $request, ChiefConsolidation $consolidation, AuditLogger $audit)
    {
        $this->authorizeSynopsis($request->user());
        $this->ensureReady($consolidation);

        return DB::transaction(function () use ($request, $consolidation, $audit) {
            $consolidation->refresh();
            $this->ensureReady($consolidation);
            $document = $this->documentFor($consolidation, $request->user());
            $this->ensureDraftEditable($document);

            if ($request->validated('version') && (int) $request->validated('version') !== $document->version) {
                return response()->json([
                    'current_version' => $document->version,
                    'current_title' => $document->title,
                    'current_body' => $document->body,
                    'current_attributions' => $document->attributions ?? [],
                ], 409);
            }

            $before = $document->body;
            $fromVersion = $document->version;
            $auditLog = $audit->log('synopsis.draft.save', $document, [
                'consolidation_id' => $consolidation->id,
                'chunk_code' => $consolidation->window_code,
                'from_version' => $fromVersion,
            ]);

            $document->forceFill([
                'writer_user_id' => $request->user()->id,
                'status' => 'draft',
                'title' => $request->validated('title') ?: $document->title,
                'body' => $request->validated('body'),
                'attributions' => $request->validated('attributions') ?? [],
                'version' => $fromVersion + 1,
                'last_audit_log_id' => $auditLog->id,
            ])->save();

            $this->recordEdit($document, $request->user(), 'save', $fromVersion, $document->version, $before, $document->body, $auditLog->id);

            return $this->detail($consolidation->fresh());
        });
    }

    public function submit(Request $request, ChiefConsolidation $consolidation, AuditLogger $audit)
    {
        $this->authorizeSynopsis($request->user());
        $this->ensureReady($consolidation);

        return DB::transaction(function () use ($request, $consolidation, $audit) {
            $consolidation->refresh();
            $this->ensureReady($consolidation);
            $document = $this->documentFor($consolidation, $request->user());
            $this->ensureEditable($document);

            if ($document->status !== 'draft') {
                return response()->json(['message' => 'Only draft synopsis documents can be submitted.'], 422);
            }

            if (! trim((string) $document->body)) {
                return response()->json(['message' => 'Synopsis draft is empty.'], 422);
            }

            $generationEvidence = $this->generationEvidenceFor($document);
            $this->ensureGeneratedSourceCurrent($document, $consolidation);
            $auditLog = $audit->log('synopsis.draft.submit', $document, [
                'consolidation_id' => $consolidation->id,
                'chunk_code' => $consolidation->window_code,
                'version' => $document->version,
                'source_sha256' => $generationEvidence['source_sha256'] ?? null,
                'generation' => $this->generationMetaFromEvidence($generationEvidence),
            ]);

            $document->forceFill([
                'status' => 'submitted',
                'submitted_at' => now(),
                'last_audit_log_id' => $auditLog->id,
            ])->save();

            $this->recordEdit($document, $request->user(), 'submit', $document->version, $document->version, $document->body, $document->body, $auditLog->id);

            return $this->detail($consolidation->fresh());
        });
    }

    public function finalise(Request $request, ChiefConsolidation $consolidation, AuditLogger $audit)
    {
        $this->authorizeSynopsis($request->user());
        $this->ensureReady($consolidation);

        return DB::transaction(function () use ($request, $consolidation, $audit) {
            $consolidation->refresh();
            $this->ensureReady($consolidation);
            $document = $this->documentFor($consolidation, $request->user());

            if ($document->status !== 'submitted') {
                return response()->json(['message' => 'Only submitted synopsis drafts can be finalised.'], 422);
            }

            $generationEvidence = $this->generationEvidenceFor($document);
            $this->ensureGeneratedSourceCurrent($document, $consolidation);
            $auditLog = $audit->log('synopsis.finalise', $document, [
                'consolidation_id' => $consolidation->id,
                'chunk_code' => $consolidation->window_code,
                'version' => $document->version,
                'ai_first_draft_stripped' => $document->ai_first_draft,
                'source_sha256' => $generationEvidence['source_sha256'] ?? null,
                'generation' => $this->generationMetaFromEvidence($generationEvidence),
            ]);

            $document->forceFill([
                'status' => 'final',
                'ai_first_draft' => false,
                'finalised_at' => now(),
                'finalised_by_user_id' => $request->user()->id,
                'last_audit_log_id' => $auditLog->id,
            ])->save();

            $this->recordEdit($document, $request->user(), 'finalise', $document->version, $document->version, $document->body, $document->body, $auditLog->id);

            return $this->detail($consolidation->fresh());
        });
    }

    public function exportPdf(Request $request, ChiefConsolidation $consolidation, SynopsisPdfService $pdf, AuditLogger $audit)
    {
        $this->authorizeSynopsis($request->user());
        $document = $this->documentFor($consolidation, $request->user());

        if ($document->status !== 'final') {
            return response()->json(['message' => 'Only final synopsis documents can be exported.'], 422);
        }

        $bytes = $pdf->render($document);
        $pdfSha256 = hash('sha256', $bytes);

        $audit->log('synopsis.pdf.export', $document, [
            'consolidation_id' => $consolidation->id,
            'chunk_code' => $consolidation->window_code,
            'version' => $document->version,
            'pdf_sha256' => $pdfSha256,
            'pdf_bytes' => strlen($bytes),
        ]);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="synopsis-'.$consolidation->window_code.'.pdf"',
            'X-Vani-Setu-Pdf-Sha256' => $pdfSha256,
        ]);
    }

    public function history(Request $request, ChiefConsolidation $consolidation): array
    {
        $this->authorizeSynopsis($request->user());
        $document = SynopsisDocument::query()->where('consolidation_id', $consolidation->id)->first();
        $edits = $document?->edits()
            ->with(['writer:id,name,employee_id', 'auditLog:id,action,this_hash,created_at,payload'])
            ->orderByDesc('id')
            ->get()
            ->map(function (SynopsisDocumentEdit $edit): array {
                $row = $edit->toArray();
                $row['audit_evidence'] = $this->auditEvidenceFromPayload($edit->auditLog?->payload);

                if (isset($row['audit_log'])) {
                    unset($row['audit_log']['payload']);
                }

                return $row;
            }) ?? collect();

        return [
            'edits' => $edits,
            'audit_events' => $document ? $this->auditEventsFor($document) : collect(),
        ];
    }

    private function documentFor(ChiefConsolidation $consolidation, User $user): SynopsisDocument
    {
        return SynopsisDocument::query()->firstOrCreate(
            ['consolidation_id' => $consolidation->id],
            [
                'sitting_id' => $consolidation->sitting_id,
                'writer_user_id' => $user->id,
                'chunk_code' => $consolidation->window_code,
                'starts_at_offset_ms' => $consolidation->starts_at_offset_ms,
                'duration_ms' => $consolidation->duration_ms,
                'status' => 'empty',
                'source_mode' => 'scratch',
                'version' => 1,
            ],
        );
    }

    private function summary(ChiefConsolidation $consolidation): array
    {
        $document = SynopsisDocument::query()->where('consolidation_id', $consolidation->id)->first();

        return [
            'id' => $consolidation->id,
            'sitting_id' => $consolidation->sitting_id,
            'chunk_code' => $consolidation->window_code,
            'starts_at_offset_ms' => $consolidation->starts_at_offset_ms,
            'duration_ms' => $consolidation->duration_ms,
            'source_status' => $consolidation->status,
            'status' => $document?->status ?? 'empty',
            'source_mode' => $document?->source_mode,
            'ai_first_draft' => $document?->ai_first_draft ?? false,
            'latest_generation' => $this->generationEvidenceFor($document),
            'version' => $document?->version,
            'sitting' => $consolidation->sitting,
            'block_count' => $consolidation->blocks()->count(),
        ];
    }

    private function detail(ChiefConsolidation $consolidation): array
    {
        $consolidation->loadMissing('sitting');
        $document = SynopsisDocument::query()
            ->where('consolidation_id', $consolidation->id)
            ->with(['edits.auditLog:id,action,this_hash,created_at'])
            ->first();
        $blocks = $consolidation->blocks()->with(['member', 'customMember'])->get();

        return [
            'chunk' => $this->summary($consolidation),
            'document' => $document ? array_merge($document->toArray(), [
                'latest_generation' => $this->generationEvidenceFor($document),
            ]) : null,
            'source_blocks' => $blocks,
        ];
    }

    private function generationEvidenceFor(?SynopsisDocument $document): ?array
    {
        $generation = $this->latestGenerationFor($document);
        $payload = $generation['payload'] ?? null;

        if (! is_array($payload) || ! is_array($payload['generation'] ?? null)) {
            return null;
        }

        return [
            ...$payload['generation'],
            'source_sha256' => $payload['source_sha256'] ?? null,
        ];
    }

    /**
     * @return array{action:?string, payload:?array<string, mixed>}|null
     */
    private function latestGenerationFor(?SynopsisDocument $document): ?array
    {
        if (! $document) {
            return null;
        }

        $edit = $document->edits()
            ->whereIn('kind', ['generate', 'author'])
            ->with('auditLog:id,action,payload')
            ->orderByDesc('id')
            ->first();

        if ($edit?->kind !== 'generate') {
            return null;
        }

        return [
            'action' => $edit->auditLog?->action,
            'payload' => $edit->auditLog?->payload,
        ];
    }

    private function generationMetaFromEvidence(?array $evidence): ?array
    {
        if (! $evidence) {
            return null;
        }

        $meta = $evidence;
        unset($meta['source_sha256']);

        return $meta;
    }

    private function auditEvidenceFromPayload(?array $payload): array
    {
        if (! $payload) {
            return [];
        }

        $generation = is_array($payload['generation'] ?? null) ? $payload['generation'] : [];

        return array_filter([
            'source_sha256' => $payload['source_sha256'] ?? null,
            'generation_provider' => $generation['provider'] ?? null,
            'generation_model' => $generation['model'] ?? null,
            'generation_fallback_reason' => $generation['fallback_reason'] ?? null,
            'generation_fallback_detail' => $generation['fallback_detail'] ?? null,
            'generation_http_status' => $generation['http_status'] ?? null,
            'generation_request_id' => $generation['request_id'] ?? null,
            'pdf_sha256' => $payload['pdf_sha256'] ?? null,
            'pdf_bytes' => $payload['pdf_bytes'] ?? null,
        ], fn ($value) => $value !== null);
    }

    private function auditEventsFor(SynopsisDocument $document)
    {
        return AuditLog::query()
            ->where('subject_type', $document->getMorphClass())
            ->where('subject_id', (string) $document->getKey())
            ->latest('id')
            ->limit(20)
            ->get(['id', 'action', 'this_hash', 'created_at', 'payload'])
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'this_hash' => $log->this_hash,
                'created_at' => $log->created_at,
                'audit_evidence' => $this->auditEvidenceFromPayload($log->payload),
            ]);
    }

    private function ensureGeneratedSourceCurrent(SynopsisDocument $document, ChiefConsolidation $consolidation): void
    {
        $generation = $this->latestGenerationFor($document);
        if (($generation['action'] ?? null) !== 'synopsis.draft.generated') {
            return;
        }

        $payload = $generation['payload'] ?? [];
        $this->ensureSourceHashCurrent($payload['source_sha256'] ?? null, $consolidation);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function ensureDraftSourceCurrent(array $draft, ChiefConsolidation $consolidation): void
    {
        $this->ensureSourceHashCurrent($draft['source_sha256'] ?? null, $consolidation);
    }

    private function ensureSourceHashCurrent(mixed $expectedHash, ChiefConsolidation $consolidation): void
    {
        $currentHash = $this->consolidationSourceHash($consolidation);

        if (! is_string($expectedHash) || $expectedHash === '' || ! hash_equals($expectedHash, $currentHash)) {
            abort(response()->json([
                'message' => 'Source blocks changed after synopsis generation. Regenerate before submit or finalise.',
                'source_sha256' => $currentHash,
                'generated_source_sha256' => $expectedHash,
            ], 409));
        }
    }

    private function consolidationSourceHash(ChiefConsolidation $consolidation): string
    {
        $payload = $consolidation->blocks()
            ->orderBy('sequence')
            ->get()
            ->map(fn ($block): array => [
                'id' => $block->id,
                'sequence' => $block->sequence,
                'version' => $block->version,
                'member_id' => $block->member_id,
                'custom_member_id' => $block->custom_member_id,
                'text_sha256' => hash('sha256', (string) $block->text),
            ])->values()->all();

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function ensureReady(ChiefConsolidation $consolidation): void
    {
        if (! in_array($consolidation->status, ['dual_committed', 'forwarded_to_js'], true)) {
            abort(response()->json(['message' => 'Chief consolidation must be accepted before synopsis drafting.'], 422));
        }
    }

    private function ensureEditable(SynopsisDocument $document): void
    {
        if ($document->status === 'final') {
            abort(response()->json(['message' => 'Final synopsis documents cannot be edited.'], 422));
        }
    }

    private function ensureDraftEditable(SynopsisDocument $document): void
    {
        $this->ensureEditable($document);

        if ($document->status === 'submitted') {
            abort(response()->json(['message' => 'Submitted synopsis documents cannot be edited.'], 422));
        }
    }

    private function authorizeSynopsis(?User $user): void
    {
        if (! $user || ! $user->hasAnyRole(['synopsis_writer', 'admin'])) {
            abort(403);
        }
    }

    private function recordEdit(
        SynopsisDocument $document,
        User $user,
        string $kind,
        ?int $fromVersion,
        ?int $toVersion,
        ?string $before,
        ?string $after,
        int $auditLogId
    ): void {
        SynopsisDocumentEdit::query()->create([
            'synopsis_document_id' => $document->id,
            'writer_user_id' => $user->id,
            'kind' => $kind,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'before_excerpt' => $before ? mb_substr($before, 0, 240) : null,
            'after_excerpt' => $after ? mb_substr($after, 0, 240) : null,
            'audit_log_id' => $auditLogId,
        ]);
    }
}
