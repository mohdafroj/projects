<?php

namespace App\Modules\Regional\Services;

use Illuminate\Support\Facades\Http;

class RegionalTranslationAdapter
{
    /**
     * @return array{translation:string, provider:string, model_version:string, confidence:float, fallback:bool}
     */
    public function translate(string $text, string $sourceLanguage, string $targetLanguage = 'hi'): array
    {
        $url = rtrim((string) config('services.ml_gateway.url', env('ML_GATEWAY_URL', '')), '/');

        if ($url !== '') {
            try {
                $response = Http::timeout(8)
                    ->retry(2, 150)
                    ->withHeaders($this->mlGatewayAuthHeaders())
                    ->post($url.'/v1/translate', [
                        'text' => $text,
                        'source_lang' => $sourceLanguage,
                        'target_lang' => $targetLanguage,
                        'provider' => 'bhashini_indictrans2',
                    ]);

                if ($response->successful() && filled($response->json('translation'))) {
                    return [
                        'translation' => (string) $response->json('translation'),
                        'provider' => (string) ($response->json('provider') ?? 'bhashini_indictrans2'),
                        'model_version' => (string) ($response->json('model_version') ?? 'external'),
                        'confidence' => (float) ($response->json('confidence') ?? 0.8),
                        'fallback' => false,
                    ];
                }
            } catch (\Throwable) {
                // External adapters are optional in dev/test; unavailable gateways use the deterministic stub.
            }
        }

        return [
            'translation' => $this->stubTranslation($text, $sourceLanguage, $targetLanguage),
            'provider' => 'deterministic-dev-stub',
            'model_version' => 'regional-stub-v1',
            'confidence' => 0.5,
            'fallback' => true,
        ];
    }

    private function stubTranslation(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        return '[Regional '.$sourceLanguage.' to '.$targetLanguage.'] '.trim($text);
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
