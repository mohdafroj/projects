<?php

namespace Tests\Feature\SpeechToSpeech;

use Tests\TestCase;

class TextOnlyAudioFallbackContractTest extends TestCase
{
    public function test_text_only_output_language_keeps_audible_fallback_target(): void
    {
        $app = file_get_contents(public_path('vanisetu-speech-to-speech/app.jsx'));
        $sarvam = file_get_contents(public_path('vanisetu-speech-to-speech/sarvam.jsx'));

        $this->assertIsString($app);
        $this->assertIsString($sarvam);
        $this->assertStringContainsString('const AUDIBLE_FALLBACK_LANG = "hi-IN";', $app);
        $this->assertStringContainsString('function hasSarvamAudioOutput(code)', $app);
        $this->assertStringContainsString('function audibleFallbackLangFor(code)', $app);
        $this->assertStringContainsString('!hasSarvamAudioOutput(code) ? AUDIBLE_FALLBACK_LANG : null', $app);
        $this->assertStringContainsString('if (audibleFallbackLang && audibleFallbackLang !== outputLang) list.push(audibleFallbackLang);', $app);
        $this->assertStringContainsString('targetLangs: targets', $app);
        $this->assertStringContainsString('const isAudibleFallback = fallbackOutput &&', $app);
        $this->assertStringContainsString('audibleFallback: !!isAudibleFallback', $app);
        $this->assertStringContainsString('fallbackFor: isAudibleFallback ? desiredOutput : null', $app);
        $this->assertStringContainsString('Audio fallback:', $app);

        $this->assertStringContainsString('{ code: "ur-IN"', $sarvam);
        $this->assertStringContainsString('audio: false', $sarvam);
    }
}
