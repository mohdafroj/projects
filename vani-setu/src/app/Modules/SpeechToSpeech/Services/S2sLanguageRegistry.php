<?php

namespace App\Modules\SpeechToSpeech\Services;

class S2sLanguageRegistry
{
    /**
     * @return array<string, array{name:string, native_name:string, audio_output:bool}>
     */
    public function all(): array
    {
        return [
            'as-IN' => ['name' => 'Assamese', 'native_name' => 'অসমীয়া', 'audio_output' => false],
            'bn-IN' => ['name' => 'Bengali', 'native_name' => 'বাংলা', 'audio_output' => true],
            'brx-IN' => ['name' => 'Bodo', 'native_name' => 'बड़ो', 'audio_output' => false],
            'doi-IN' => ['name' => 'Dogri', 'native_name' => 'डोगरी', 'audio_output' => false],
            'en-IN' => ['name' => 'English', 'native_name' => 'English', 'audio_output' => true],
            'gu-IN' => ['name' => 'Gujarati', 'native_name' => 'ગુજરાતી', 'audio_output' => true],
            'hi-IN' => ['name' => 'Hindi', 'native_name' => 'हिन्दी', 'audio_output' => true],
            'kn-IN' => ['name' => 'Kannada', 'native_name' => 'ಕನ್ನಡ', 'audio_output' => true],
            'kok-IN' => ['name' => 'Konkani', 'native_name' => 'कोंकणी', 'audio_output' => false],
            'ks-IN' => ['name' => 'Kashmiri', 'native_name' => 'कॉशुर', 'audio_output' => false],
            'mai-IN' => ['name' => 'Maithili', 'native_name' => 'मैथिली', 'audio_output' => false],
            'ml-IN' => ['name' => 'Malayalam', 'native_name' => 'മലയാളം', 'audio_output' => true],
            'mni-IN' => ['name' => 'Manipuri', 'native_name' => 'মৈতৈলোন্', 'audio_output' => false],
            'mr-IN' => ['name' => 'Marathi', 'native_name' => 'मराठी', 'audio_output' => true],
            'ne-IN' => ['name' => 'Nepali', 'native_name' => 'नेपाली', 'audio_output' => false],
            'od-IN' => ['name' => 'Odia', 'native_name' => 'ଓଡ଼ିଆ', 'audio_output' => true],
            'pa-IN' => ['name' => 'Punjabi', 'native_name' => 'ਪੰਜਾਬੀ', 'audio_output' => true],
            'sa-IN' => ['name' => 'Sanskrit', 'native_name' => 'संस्कृतम्', 'audio_output' => false],
            'sat-IN' => ['name' => 'Santali', 'native_name' => 'ᱥᱟᱱᱛᱟᱲᱤ', 'audio_output' => false],
            'sd-IN' => ['name' => 'Sindhi', 'native_name' => 'سنڌي', 'audio_output' => false],
            'ta-IN' => ['name' => 'Tamil', 'native_name' => 'தமிழ்', 'audio_output' => true],
            'te-IN' => ['name' => 'Telugu', 'native_name' => 'తెలుగు', 'audio_output' => true],
            'ur-IN' => ['name' => 'Urdu', 'native_name' => 'اردو', 'audio_output' => false],
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultTargets(): array
    {
        return ['hi-IN'];
    }

    /**
     * @return list<string>
     */
    public function audioOutputLanguages(): array
    {
        return array_keys(array_filter($this->all(), fn (array $language) => $language['audio_output']));
    }

    public function hasAudioOutput(string $code): bool
    {
        return (bool) ($this->all()[$code]['audio_output'] ?? false);
    }

    public function resolve(string $code, string $fallback = 'en-IN'): string
    {
        $code = trim($code);

        return $this->exists($code) ? $code : $fallback;
    }

    /**
     * @param  list<string>  $targetLangs
     * @return list<string>
     */
    public function withAudibleFallback(array $targetLangs, string $fallback = 'hi-IN'): array
    {
        $normalized = array_values(array_unique($targetLangs));
        if ($normalized === []) {
            return $this->defaultTargets();
        }

        foreach ($normalized as $code) {
            if ($this->hasAudioOutput($code)) {
                return $normalized;
            }
        }

        if ($this->exists($fallback)) {
            $normalized[] = $fallback;
        }

        return array_values(array_unique($normalized));
    }

    public function exists(string $code): bool
    {
        return array_key_exists($code, $this->all());
    }

    public function label(string $code): string
    {
        $language = $this->all()[$code] ?? null;

        return $language ? $language['name'].' · '.$language['native_name'] : $code;
    }
}
