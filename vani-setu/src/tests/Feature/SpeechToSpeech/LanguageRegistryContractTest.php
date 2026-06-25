<?php

namespace Tests\Feature\SpeechToSpeech;

use App\Modules\SpeechToSpeech\Services\S2sLanguageRegistry;
use Tests\TestCase;

class LanguageRegistryContractTest extends TestCase
{
    public function test_registry_contains_english_plus_twenty_two_scheduled_languages(): void
    {
        $registry = app(S2sLanguageRegistry::class);
        $languages = $registry->all();
        $scheduled = array_diff(array_keys($languages), ['en-IN']);

        $this->assertCount(23, $languages);
        $this->assertCount(22, $scheduled);
        $this->assertArrayHasKey('en-IN', $languages);
        $this->assertArrayHasKey('hi-IN', $languages);
        $this->assertArrayHasKey('bn-IN', $languages);
        $this->assertTrue($registry->hasAudioOutput('en-IN'));
        $this->assertTrue($registry->hasAudioOutput('hi-IN'));
    }

    public function test_text_only_listener_target_gets_audible_fallback(): void
    {
        $registry = app(S2sLanguageRegistry::class);

        $this->assertSame(['ur-IN', 'hi-IN'], $registry->withAudibleFallback(['ur-IN']));
        $this->assertSame('en-IN', $registry->resolve('xx-IN', 'en-IN'));
        $this->assertSame('bn-IN', $registry->resolve('bn-IN', 'en-IN'));
    }
}
