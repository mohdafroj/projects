<?php

namespace App\Modules\Regional\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Regional\Models\RegionalCase;
use App\Modules\Regional\Models\RegionalCrossCheck;
use App\Modules\Regional\Services\RegionalLanguageDetector;
use App\Modules\Regional\Services\RegionalRoutingService;
use App\Modules\Regional\Services\RegionalTranslationAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RegionalController extends Controller
{
    public function queue(Request $request)
    {
        return RegionalCase::query()
            ->with(['slot.sitting', 'block', 'specialist:id,name,employee_id'])
            ->where('specialist_user_id', $request->user()->id)
            ->whereIn('status', ['routed', 'translated', 'needs_revision'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function store(Request $request, RegionalLanguageDetector $detector, RegionalRoutingService $routing, AuditLogger $audit)
    {
        $data = $request->validate([
            'source_text' => ['required_without:block_id', 'string', 'min:2'],
            'block_id' => ['nullable', 'integer', 'exists:blocks,id'],
            'domain' => ['nullable', Rule::in(['parliamentary', 'economic', 'legal'])],
            'target_language' => ['nullable', Rule::in(['hi', 'en'])],
        ]);

        $block = isset($data['block_id']) ? Block::query()->with('slot.sitting')->findOrFail($data['block_id']) : null;
        $sourceText = (string) ($data['source_text'] ?? $block?->text ?? $block?->ai_text);
        $detected = $detector->detect($sourceText);
        $target = $data['target_language'] ?? 'hi';
        abort_if(in_array($detected['language'], ['en', 'hi', 'und'], true), 422, 'Regional intake requires a non-EN/HI Indian language.');

        $specialist = $routing->specialistFor($detected['language']);
        abort_unless($specialist, 422, 'No regional specialist is available for '.$detected['language'].'.');

        return DB::transaction(function () use ($data, $block, $sourceText, $detected, $target, $specialist, $request, $audit) {
            $case = RegionalCase::query()->create([
                'sitting_id' => $block?->slot?->sitting_id,
                'slot_id' => $block?->slot_id,
                'block_id' => $block?->id,
                'requester_user_id' => $request->user()->id,
                'specialist_user_id' => $specialist->id,
                'source_language' => $detected['language'],
                'target_language' => $target,
                'detector' => $detected['detector'],
                'detection_confidence' => $detected['confidence'],
                'domain' => $data['domain'] ?? 'parliamentary',
                'source_text' => $sourceText,
                'routing_meta' => [
                    'specialist_employee_id' => $specialist->employee_id,
                    'language_pair' => $detected['language'].'_to_'.$target,
                ],
            ]);

            $audit->log($case->block_id ? 'regional.block.routed' : 'regional.case.routed', $case, [
                'source_language' => $case->source_language,
                'target_language' => $case->target_language,
                'specialist_user_id' => $case->specialist_user_id,
            ]);

            return response()->json($this->payload($case), 201);
        });
    }

    public function show(Request $request, RegionalCase $case)
    {
        $this->authorizeCase($request, $case);

        return $this->payload($case);
    }

    public function translate(Request $request, RegionalCase $case, RegionalTranslationAdapter $adapter, AuditLogger $audit)
    {
        $this->authorizeCase($request, $case);
        abort_if($case->isSealed(), 423, 'Regional case is sealed.');
        $data = $request->validate([
            'specialist_translation' => ['nullable', 'string', 'min:2'],
        ]);

        return DB::transaction(function () use ($case, $adapter, $audit, $data) {
            $result = $adapter->translate($case->source_text, $case->source_language, $case->target_language);
            $locked = RegionalCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'status' => 'translated',
                'machine_translation' => $result['translation'],
                'specialist_translation' => $data['specialist_translation'] ?? $result['translation'],
                'translation_meta' => [
                    'provider' => $result['provider'],
                    'model_version' => $result['model_version'],
                    'confidence' => $result['confidence'],
                    'fallback' => $result['fallback'],
                    'translated_at' => now()->toIso8601String(),
                ],
            ])->save();

            $audit->log('regional.case.translated', $locked, $locked->translation_meta ?? []);

            return $this->payload($locked);
        });
    }

    public function crossCheck(Request $request, RegionalCase $case, AuditLogger $audit)
    {
        $this->authorizeCase($request, $case);
        abort_unless($case->status === 'translated', 422, 'Translate before cross-check.');
        $data = $request->validate([
            'result' => ['required', Rule::in(['passed', 'needs_revision'])],
            'issues' => ['nullable', 'array'],
            'issues.*' => ['string', 'max:255'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($case, $request, $audit, $data) {
            $locked = RegionalCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $auditLog = $audit->log('regional.case.cross_checked', $locked, $data);
            $check = RegionalCrossCheck::query()->create([
                'case_id' => $locked->id,
                'reviewer_user_id' => $request->user()->id,
                'result' => $data['result'],
                'issues' => $data['issues'] ?? [],
                'score' => $data['score'] ?? ($data['result'] === 'passed' ? 100 : 70),
                'notes' => $data['notes'] ?? null,
                'audit_log_id' => $auditLog->id,
            ]);
            $locked->forceFill(['status' => $data['result'] === 'passed' ? 'cross_checked' : 'needs_revision'])->save();

            return ['case' => $locked->fresh(), 'cross_check' => $check->fresh()];
        });
    }

    public function commit(Request $request, RegionalCase $case, AuditLogger $audit)
    {
        $this->authorizeCase($request, $case);
        abort_unless($case->status === 'cross_checked', 422, 'A passed cross-check is required before commit.');

        return DB::transaction(function () use ($case, $audit) {
            $locked = RegionalCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            if ($locked->block_id) {
                $locked->block()->update([
                    'translated_text' => $locked->specialist_translation,
                    'version' => DB::raw('version + 1'),
                ]);
            }
            $locked->forceFill(['status' => 'committed'])->save();
            $audit->log('regional.case.committed', $locked, [
                'block_id' => $locked->block_id,
                'source_language' => $locked->source_language,
            ]);

            return $this->payload($locked);
        });
    }

    public function history(Request $request, RegionalCase $case)
    {
        $this->authorizeCase($request, $case);

        return [
            'case' => $case->fresh(),
            'cross_checks' => $case->crossChecks()->with('auditLog:id,action,this_hash,created_at')->latest()->get(),
            'audit' => AuditLog::query()
                ->where('subject_type', $case->getMorphClass())
                ->where('subject_id', (string) $case->id)
                ->where('action', 'like', 'regional.%')
                ->orderBy('id')
                ->get(),
        ];
    }

    private function authorizeCase(Request $request, RegionalCase $case): void
    {
        abort_unless((int) $case->specialist_user_id === (int) $request->user()->id, 403);
        abort_unless(in_array($case->source_language.'_to_'.$case->target_language, $request->user()->language_competencies ?? [], true), 403);
    }

    private function payload(RegionalCase $case): array
    {
        return [
            'case' => $case->fresh(['slot.sitting', 'block', 'requester:id,name,employee_id', 'specialist:id,name,employee_id']),
            'cross_checks' => $case->crossChecks()->latest()->get(),
        ];
    }
}
