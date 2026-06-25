<?php

namespace App\Modules\Translator\Services;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Translator\Models\TranslatorAssignment;
use App\Modules\Translator\Models\TranslatorCommit;
use Illuminate\Support\Facades\DB;

class VersionFinalisationService
{
    public const SEALED_STATUSES = [
        'hv_draft_finalised',
        'ev_draft_finalised',
        'supervisor_review',
        'director_review',
        'translator_committed',
        'forwarded',
    ];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function finaliseDraft(TranslatorAssignment $assignment, User $actor, array $data): array
    {
        return DB::transaction(function () use ($assignment, $actor, $data) {
            /** @var TranslatorAssignment $locked */
            $locked = TranslatorAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $currentVersion = $this->slotVersion($locked);

            if ((int) $data['slot_version'] !== $currentVersion) {
                return [
                    'conflict' => true,
                    'status' => 409,
                    'body' => [
                        'message' => 'Version conflict.',
                        'resolution' => 'reload_latest_draft',
                        'current_slot_version' => $currentVersion,
                    ],
                ];
            }

            if (in_array($locked->status, self::SEALED_STATUSES, true)) {
                return [
                    'conflict' => false,
                    'status' => 423,
                    'body' => ['message' => 'Assignment is sealed.'],
                ];
            }

            $missing = $locked->blocks()
                ->get()
                ->filter(fn (Block $block) => blank($block->translated_text ?: $block->ai_text ?: $block->text))
                ->pluck('id')
                ->values();

            if ($missing->isNotEmpty()) {
                return [
                    'conflict' => false,
                    'status' => 422,
                    'body' => [
                        'message' => 'Draft contains empty blocks.',
                        'block_ids' => $missing,
                    ],
                ];
            }

            $draftType = $data['draft_type'];
            $regionalFlag = (bool) ($data['regional_language_flag'] ?? false);
            $auditLog = $this->audit->log('translator.draft.finalised', $locked, [
                'slot_id' => $locked->slot_id,
                'language_pair' => $locked->language_pair,
                'draft_type' => $draftType,
                'slot_version' => $currentVersion,
                'regional_language_flag' => $regionalFlag,
                'regional_language_code' => $data['regional_language_code'] ?? null,
                'next_stage' => 'supervisor',
                'note' => $data['note'] ?? null,
            ]);

            $commit = TranslatorCommit::query()->create([
                'assignment_id' => $locked->id,
                'translator_user_id' => $actor->id,
                'block_count' => $locked->blocks()->count(),
                'edit_count' => $locked->edits()->count(),
                'ai_acceptance_rate' => $locked->aiAcceptanceRate(),
                'committed_at' => now(),
                'committed_audit_log_id' => $auditLog->id,
            ]);

            $locked->forceFill([
                'status' => "{$draftType}_draft_finalised",
                'ai_translation_meta' => $this->mergeMeta($locked, [
                    'draft_finalisation' => [
                        'draft_type' => $draftType,
                        'slot_version' => $currentVersion,
                        'regional_language_flag' => $regionalFlag,
                        'regional_language_code' => $data['regional_language_code'] ?? null,
                        'finalised_by' => $actor->id,
                        'finalised_at' => now()->toIso8601String(),
                        'audit_log_id' => $auditLog->id,
                    ],
                    'forward_chain' => ['translator', 'supervisor', 'director'],
                    'current_stage' => 'translator',
                ]),
            ])->save();

            return [
                'conflict' => false,
                'status' => 200,
                'body' => [
                    'assignment' => $locked->fresh(),
                    'commit' => $commit->fresh(),
                    'slot_version' => $currentVersion,
                    'next_stage' => 'supervisor',
                ],
            ];
        });
    }

    public function forwardToSupervisor(TranslatorAssignment $assignment, User $actor, ?string $note = null): array
    {
        return DB::transaction(function () use ($assignment, $actor, $note) {
            /** @var TranslatorAssignment $locked */
            $locked = TranslatorAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['hv_draft_finalised', 'ev_draft_finalised'], true)) {
                return ['status' => 422, 'body' => ['message' => 'Draft must be finalised before supervisor handoff.']];
            }

            $auditLog = $this->audit->log('translator.forward.supervisor', $locked, [
                'from_stage' => 'translator',
                'to_stage' => 'supervisor',
                'slot_id' => $locked->slot_id,
                'language_pair' => $locked->language_pair,
                'note' => $note,
            ]);

            $locked->forceFill([
                'status' => 'supervisor_review',
                'ai_translation_meta' => $this->mergeMeta($locked, [
                    'current_stage' => 'supervisor',
                    'supervisor_handoff' => [
                        'forwarded_by' => $actor->id,
                        'forwarded_at' => now()->toIso8601String(),
                        'audit_log_id' => $auditLog->id,
                        'note' => $note,
                    ],
                ]),
            ])->save();

            return ['status' => 200, 'body' => ['assignment' => $locked->fresh(), 'next_stage' => 'director']];
        });
    }

    public function forwardToDirector(TranslatorAssignment $assignment, User $actor, ?string $note = null): array
    {
        return DB::transaction(function () use ($assignment, $actor, $note) {
            /** @var TranslatorAssignment $locked */
            $locked = TranslatorAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'supervisor_review') {
                return ['status' => 422, 'body' => ['message' => 'Assignment must be in supervisor review before Director handoff.']];
            }

            $auditLog = $this->audit->log('translator.forward.director', $locked, [
                'from_stage' => 'supervisor',
                'to_stage' => 'director',
                'slot_id' => $locked->slot_id,
                'language_pair' => $locked->language_pair,
                'note' => $note,
            ]);

            $locked->forceFill([
                'status' => 'director_review',
                'ai_translation_meta' => $this->mergeMeta($locked, [
                    'current_stage' => 'director',
                    'director_handoff' => [
                        'forwarded_by' => $actor->id,
                        'forwarded_at' => now()->toIso8601String(),
                        'audit_log_id' => $auditLog->id,
                        'note' => $note,
                    ],
                ]),
            ])->save();

            return ['status' => 200, 'body' => ['assignment' => $locked->fresh(), 'next_stage' => 'director']];
        });
    }

    public function slotVersion(TranslatorAssignment $assignment): int
    {
        $stats = $assignment->blocks()
            ->selectRaw('COUNT(*) as block_count, COALESCE(SUM(version), 0) as version_sum, COALESCE(MIN(version), 0) as min_version')
            ->first();

        $count = (int) ($stats->block_count ?? 0);
        $sum = (int) ($stats->version_sum ?? 0);
        $min = (int) ($stats->min_version ?? 0);

        if ($count === 0) {
            return 0;
        }

        return $min <= 0 ? $sum : $sum - $count + 1;
    }

    private function mergeMeta(TranslatorAssignment $assignment, array $meta): array
    {
        return array_replace_recursive($assignment->ai_translation_meta ?? [], $meta);
    }
}
