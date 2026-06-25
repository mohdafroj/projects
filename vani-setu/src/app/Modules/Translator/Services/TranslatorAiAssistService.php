<?php

namespace App\Modules\Translator\Services;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Translator\Models\TranslatorAssignment;
use Illuminate\Support\Facades\Http;

class TranslatorAiAssistService
{
    public function __construct(
        private readonly TerminologyEnforcer $terminology,
        private readonly AuditLogger $audit,
    ) {}

    public function requestAi(TranslatorAssignment $assignment): TranslatorAssignment
    {
        $started = microtime(true);
        [$sourceLang, $targetLang] = explode('_to_', $assignment->language_pair, 2);
        $provider = 'indictrans2';
        $modelVersion = 'local-deterministic';
        $count = 0;

        $assignment->forceFill(['status' => 'ai_pending'])->save();

        $assignment->blocks()->get()->each(function (Block $block) use ($sourceLang, $targetLang, $assignment, &$modelVersion, &$count): void {
            $source = (string) ($block->translated_text ?: $block->text ?: $block->ai_text);
            $request = Http::timeout(8)
                ->retry(2, 150)
                ->withHeaders($this->mlGatewayAuthHeaders());

            $response = $request->post(rtrim(config('services.ml_gateway.url', env('ML_GATEWAY_URL', 'http://ml-gateway:8000')), '/').'/v1/translate', [
                    'text' => $source,
                    'source_lang' => $sourceLang,
                    'target_lang' => $targetLang,
                ]);

            if ($response->successful()) {
                $payload = $response->json();
                $suggestion = (string) ($payload['translation'] ?? '');
                $modelVersion = (string) ($payload['model_version'] ?? $modelVersion);
            } else {
                $suggestion = $this->fallbackTranslation($source, $targetLang);
            }

            $block->forceFill([
                'ai_action' => 'translated',
                'ai_text' => $this->terminology->enforce($suggestion, $assignment->language_pair),
            ])->save();
            $count++;
        });

        $meta = [
            'provider' => $provider,
            'model_version' => $modelVersion,
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
            'block_count' => $count,
            'requested_at' => now()->toIso8601String(),
        ];

        $audit = $this->audit->log('translator.ai.requested', $assignment, $meta);

        $assignment->forceFill([
            'status' => 'in_review',
            'ai_translation_meta' => array_merge($meta, ['audit_log_id' => $audit->id]),
        ])->save();

        return $assignment->fresh(['slot.blocks', 'translator']);
    }

    private function fallbackTranslation(string $source, string $targetLang): string
    {
        if ($targetLang === 'hi') {
            return '[AI draft HI] '.$source;
        }

        return '[AI draft '.$targetLang.'] '.$source;
    }

    /**
     * @return array<string, string>
     */
    private function mlGatewayAuthHeaders(): array
    {
        $token = trim((string) config('services.ml_gateway.service_token', ''));

        return $token === '' ? [] : ['Authorization' => 'Bearer '.$token];
    }
}
