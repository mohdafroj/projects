<?php

namespace App\Modules\SpeechToSpeech\Services;

use App\Modules\Core\Models\User;
use App\Modules\SpeechToSpeech\Models\S2sRuntimeConfig;

class S2sConfigRepository
{
    public function __construct(
        private readonly S2sLanguageRegistry $languages,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pipeline(): array
    {
        $stored = S2sRuntimeConfig::query()->where('config_key', 'pipeline')->value('config_value');

        return array_replace_recursive($this->defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function updatePipeline(array $config, ?User $editor = null): array
    {
        $merged = array_replace_recursive($this->pipeline(), $config);

        S2sRuntimeConfig::query()->updateOrCreate(
            ['config_key' => 'pipeline'],
            [
                'config_value' => $merged,
                'edited_by_user_id' => $editor?->id,
            ],
        );

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'announcement_prefix' => 'AI translated voice. Please rely on the original voice for confirmation. We cannot guarantee the original language translation.',
            'default_mode' => 'live',
            'default_listener_scope' => 'hybrid',
            'default_input_source' => 'microphone',
            'default_source_language' => 'auto',
            'target_languages' => $this->languages->defaultTargets(),
            'audio_output_languages' => $this->languages->audioOutputLanguages(),
            'fallback_chain' => [
                ['provider' => 'sarvam', 'role' => 'primary'],
                ['provider' => 'internal_text_only', 'role' => 'fallback'],
            ],
            'archive' => [
                'store_source_audio' => true,
                'store_translated_audio' => true,
                'store_transcripts' => true,
            ],
            'latency_policy' => [
                'mode' => 'balanced',
                'prefer_realtime' => true,
            ],
            // Sarvam stage knobs pinned from the Windows PoC; see
            // docs/vani-setu-poc-handover.md for why each value is set.
            'sarvam' => [
                'stt' => [
                    'model' => (string) config('services.sarvam.stt.model', 'saaras:v3'),
                    'mode' => (string) config('services.sarvam.stt.mode', 'codemix'),
                    'with_diarization' => false,
                ],
                'translate' => [
                    'model' => (string) config('services.sarvam.translate.model', 'mayura:v1'),
                    'mode' => (string) config('services.sarvam.translate.mode', 'formal'),
                    'enable_preprocessing' => true,
                ],
                'tts' => [
                    'model' => (string) config('services.sarvam.tts.model', 'bulbul:v3'),
                    'pace' => (float) config('services.sarvam.tts.pace', 1.1),
                    'sample_rate' => (int) config('services.sarvam.tts.sample_rate', 22050),
                    'codec' => (string) config('services.sarvam.tts.codec', 'wav'),
                    'enable_preprocessing' => true,
                ],
            ],
            // Bulbul v3 speaker roster, verbatim from Sarvam's HTTP 400 response.
            // DO NOT mix v2 names in here (anushka/manisha/vidya/arya/abhilash/
            // karun/hitesh) — v3 returns HTTP 400. Source: PoC, 2026-05-21.
            'voice_roster' => [
                'bulbul:v3' => [
                    'male' => [
                        'shubh', 'aditya', 'ashutosh', 'rahul', 'rohan', 'amit', 'dev',
                        'ratan', 'varun', 'manan', 'sumit', 'kabir', 'aayan', 'advait',
                        'anand', 'tarun', 'sunny', 'mani', 'gokul', 'vijay', 'mohit',
                        'rehan', 'soham',
                    ],
                    'female' => [
                        'ritu', 'priya', 'neha', 'pooja', 'simran', 'kavya', 'ishita',
                        'shreya', 'roopa', 'tanya', 'shruti', 'suhani', 'kavitha',
                        'rupali', 'niharika',
                    ],
                ],
            ],
        ];
    }
}
