<?php

namespace App\Modules\Capture\Services;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Search\Services\ArtifactCatalogService;
use Illuminate\Support\Facades\DB;

class ReporterAudioFinalizer
{
    public function __construct(
        private readonly ReporterAudioStorage $storage,
        private readonly TijoriAsrGateway $asr,
        private readonly AuditLogger $audit,
        private readonly ArtifactCatalogService $artifacts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function finalizeBySlotId(int $slotId): array
    {
        $slot = Slot::query()->findOrFail($slotId);

        return $this->finalize($slot);
    }

    /**
     * @return array<string, mixed>
     */
    public function finalize(Slot $slot): array
    {
        $chunks = $this->storage->chunks($slot);

        if ($chunks->isEmpty()) {
            $result = [
                'slot_id' => $slot->id,
                'closed' => false,
                'message' => 'No reporter audio chunks found.',
            ];

            $this->audit->log('reporter.audio.finalize.skipped', $slot, $result);

            return $result;
        }

        try {
            $final = $this->storage->assembleFinal($slot, $chunks);
            $asr = $this->asr->transcribeSlotAudio($slot, $final['path']);
            $asrBlock = $this->persistTranscriptBlock($slot, $asr);

            $result = [
                'slot_id' => $slot->id,
                'chunk_count' => $chunks->count(),
                'path' => $final['path'],
                'uri' => $final['uri'],
                'bytes' => $final['bytes'],
                'sha256' => $final['sha256'],
                'storage_provider' => 'minio',
                'asr' => $asr,
                'asr_block_id' => $asrBlock?->id,
                'closed' => true,
            ];

            try {
                $artifact = $this->artifacts->registerStoredObject([
                    'disk' => $this->storage->diskName(),
                    'path' => $final['path'],
                    'storage_uri' => $final['uri'],
                    'mime_type' => $this->guessMimeType($final['path']),
                    'title' => 'Reporter audio slot '.$slot->id,
                    'source_module' => 'capture',
                    'subject_type' => Slot::class,
                    'subject_id' => $slot->id,
                    'size_bytes' => $final['bytes'],
                    'sha256' => $final['sha256'],
                    'tags' => ['reporter-audio', 'asr-source'],
                    'metadata' => [
                        'slot_id' => $slot->id,
                        'chunk_count' => $chunks->count(),
                        'asr_status' => $asr['status'] ?? null,
                        'asr_block_id' => $asrBlock?->id,
                    ],
                    'search_text' => $asr['transcript'] ?? (is_array($asr['detail'] ?? null) ? json_encode($asr['detail']) : null),
                ]);

                $result['artifact_id'] = $artifact->id;
            } catch (\Throwable $exception) {
                $this->audit->log('artifact.catalog.failed', $slot, [
                    'source_module' => 'capture',
                    'path' => $final['path'],
                    'error' => $exception->getMessage(),
                ]);
            }

            $this->audit->log('reporter.audio.closed', $slot, $result);

            return $result;
        } catch (\Throwable $exception) {
            $result = [
                'slot_id' => $slot->id,
                'chunk_count' => $chunks->count(),
                'closed' => false,
                'error' => $exception->getMessage(),
            ];

            $this->audit->log('reporter.audio.finalize.failed', $slot, $result);

            throw $exception;
        }
    }

    private function persistTranscriptBlock(Slot $slot, array $asr): ?Block
    {
        $transcript = trim((string) ($asr['transcript'] ?? ''));
        if ($transcript === '') {
            return null;
        }

        return DB::transaction(function () use ($slot, $asr, $transcript): ?Block {
            /** @var Slot $lockedSlot */
            $lockedSlot = Slot::query()->whereKey($slot->id)->lockForUpdate()->firstOrFail();

            if ($lockedSlot->blocks()->exists()) {
                return null;
            }

            $language = $this->normalizeLanguage($asr['language'] ?? null);
            $block = Block::withoutEvents(fn () => Block::query()->create([
                'slot_id' => $lockedSlot->id,
                'sequence' => 1,
                'start_ms' => 0,
                'end_ms' => max(30000, (int) $lockedSlot->duration_ms),
                'original_lang' => $language,
                'chief_lang' => $language === 'hi' ? 'hi' : 'en',
                'ai_action' => 'native',
                'ai_text' => $transcript,
                'text' => $transcript,
                'version' => 1,
                'reporter_edit_count' => 0,
            ]));

            if ($lockedSlot->status === 'open') {
                $lockedSlot->forceFill(['status' => 'in_progress'])->save();
            }

            $this->audit->log('reporter.audio.asr_block_created', $block, [
                'slot_id' => $lockedSlot->id,
                'language' => $language,
                'confidence' => $asr['confidence'] ?? null,
                'provider' => $asr['provider'] ?? null,
                'status' => $asr['status'] ?? null,
                'text_length' => mb_strlen($transcript),
            ]);

            return $block;
        });
    }

    private function guessMimeType(string $audioPath): string
    {
        return match (strtolower(pathinfo($audioPath, PATHINFO_EXTENSION))) {
            'wav' => 'audio/wav',
            'mp3' => 'audio/mpeg',
            'm4a', 'mp4' => 'audio/mp4',
            'ogg' => 'audio/ogg',
            default => 'audio/webm',
        };
    }

    private function normalizeLanguage(?string $language): string
    {
        $language = strtolower((string) $language);
        $language = str_contains($language, '-') ? strtok($language, '-') : $language;

        return in_array($language, ['en', 'hi', 'ta', 'ur', 'bn', 'mr'], true) ? $language : 'en';
    }
}
