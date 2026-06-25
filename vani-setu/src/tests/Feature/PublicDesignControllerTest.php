<?php

namespace Tests\Feature;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Translator\Models\TranslatorAssignment;
use App\Modules\Translator\Models\TranslatorGlossary;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssertsAuditChains;
use Tests\TestCase;

class PublicDesignControllerTest extends TestCase
{
    use AssertsAuditChains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_home_page_uses_the_governed_public_design_shell(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Vani Setu Public Dashboard')
            ->assertSee('3 Workflows')
            ->assertSee('AI-powered language bridge')
            ->assertSee('Speech To Speech')
            ->assertSee('Speech To Text')
            ->assertSee('Text To Text')
            ->assertDontSee('API Health')
            ->assertSee('bg-canvas', false)
            ->assertDontSee('Laravel News');
    }

    public function test_design_standard_page_is_publicly_available(): void
    {
        $response = $this->get('/design-standard');

        $response
            ->assertOk()
            ->assertSee('Standard Design Principles')
            ->assertSee('PublicDesignController::renderPublicPage()');
    }

    public function test_speech_to_text_page_is_publicly_available(): void
    {
        $response = $this->get('/speech-to-text');

        $response
            ->assertOk()
            ->assertSee('AI-powered language bridge')
            ->assertDontSee('Reporter Capture Console')
            ->assertSee('Create transcript block')
            ->assertSee('Capture to handoff')
            ->assertSee('Record audio');
    }

    public function test_text_to_text_page_is_publicly_available(): void
    {
        $response = $this->get('/text-to-text');

        $response
            ->assertOk()
            ->assertSee('AI-powered language bridge')
            ->assertSee('Create translation task')
            ->assertSee('Terminology control');
    }

    public function test_public_speech_to_text_can_capture_correct_and_handoff_to_translation(): void
    {
        $this->withoutMiddleware();

        $this->post('/speech-to-text/captures', [
            'slot_code' => 'PUBLIC-SMOKE',
            'topic' => 'Public workflow smoke',
            'language' => 'en',
            'speaker_name' => 'Chair',
            'source_text' => 'The House will now take up the listed business.',
        ])->assertRedirect();

        $slot = Slot::query()->where('code', 'PUBLIC-SMOKE')->firstOrFail();
        $block = Block::query()->where('slot_id', $slot->id)->firstOrFail();

        $this->post("/speech-to-text/blocks/{$block->id}", [
            'text' => 'The House will now take up the listed business after Question Hour.',
        ])->assertRedirect(route('public.s2t', ['selected_slot' => $slot->id]));

        $this->post("/speech-to-text/slots/{$slot->id}/handoff")
            ->assertRedirect();

        $this->assertDatabaseHas('translator_assignments', [
            'slot_id' => $slot->id,
            'language_pair' => 'en_to_hi',
            'status' => 'open',
        ]);
        $this->assertAuditActionsChained('speech_to_text', [
            'capture.block.public_created',
            'capture.block.public_corrected',
            'capture.slot.public_handoff',
        ]);
    }

    public function test_public_speech_to_text_can_create_block_from_uploaded_audio_asr(): void
    {
        $this->withoutMiddleware();
        Storage::fake('vani_audio');
        config([
            'filesystems.reporter_audio_disk' => 'vani_audio',
            'services.tijori.asr_url' => 'http://tijori.test/v1/asr/sarvam',
            'services.tijori.retries' => 0,
        ]);
        Http::fake([
            'tijori.test/*' => Http::response([
                'stt' => [
                    'text' => 'Audio transcript from Tijori.',
                    'language_code' => 'en-IN',
                    'confidence' => 0.91,
                ],
            ], 200),
        ]);

        $this->post('/speech-to-text/captures', [
            'slot_code' => 'PUBLIC-AUDIO',
            'topic' => 'Audio workflow smoke',
            'language' => 'en',
            'audio' => UploadedFile::fake()->create('floor.wav', 12, 'audio/wav'),
        ])->assertRedirect();

        $slot = Slot::query()->where('code', 'PUBLIC-AUDIO')->firstOrFail();
        $this->assertDatabaseHas('blocks', [
            'slot_id' => $slot->id,
            'ai_text' => 'Audio transcript from Tijori.',
            'text' => 'Audio transcript from Tijori.',
            'original_lang' => 'en',
        ]);
        Storage::disk('vani_audio')->assertExists('public-s2t/'.$slot->id.'/floor.wav');
        $this->assertAuditActionsChained('speech_to_text', [
            'capture.block.public_created',
        ]);
    }

    public function test_public_speech_to_text_prefers_pasted_text_over_asr_text(): void
    {
        $this->withoutMiddleware();
        Storage::fake('vani_audio');
        config([
            'filesystems.reporter_audio_disk' => 'vani_audio',
            'services.tijori.asr_url' => 'http://tijori.test/v1/asr/sarvam',
            'services.tijori.retries' => 0,
        ]);
        Http::fake([
            'tijori.test/*' => Http::response(['transcript' => 'Machine transcript.'], 200),
        ]);

        $this->post('/speech-to-text/captures', [
            'slot_code' => 'PUBLIC-TEXT-WINS',
            'topic' => 'Text precedence',
            'language' => 'en',
            'source_text' => 'Corrected reporter transcript.',
            'audio' => UploadedFile::fake()->create('floor.webm', 8, 'audio/webm'),
        ])->assertRedirect();

        $slot = Slot::query()->where('code', 'PUBLIC-TEXT-WINS')->firstOrFail();
        $this->assertDatabaseHas('blocks', [
            'slot_id' => $slot->id,
            'text' => 'Corrected reporter transcript.',
        ]);
    }

    public function test_public_speech_to_text_requires_text_when_asr_returns_no_transcript(): void
    {
        $this->withoutMiddleware();
        Storage::fake('vani_audio');
        config([
            'filesystems.reporter_audio_disk' => 'vani_audio',
            'services.tijori.asr_url' => 'http://tijori.test/v1/asr/sarvam',
            'services.tijori.retries' => 0,
        ]);
        Http::fake(['tijori.test/*' => Http::response(['queued' => true], 202)]);

        $this->post('/speech-to-text/captures', [
            'slot_code' => 'PUBLIC-NO-ASR',
            'topic' => 'No transcript',
            'language' => 'en',
            'audio' => UploadedFile::fake()->create('floor.webm', 8, 'audio/webm'),
        ])->assertSessionHasErrors('source_text');

        $this->assertDatabaseMissing('slots', ['code' => 'PUBLIC-NO-ASR']);
    }

    public function test_public_text_to_text_can_create_glossary_and_store_translation(): void
    {
        $this->withoutMiddleware();

        $this->post('/speech-to-text/captures', [
            'slot_code' => 'PUBLIC-T2T',
            'topic' => 'Translation smoke',
            'language' => 'en',
            'source_text' => 'The Bill is listed for consideration.',
        ])->assertRedirect();

        $slot = Slot::query()->where('code', 'PUBLIC-T2T')->firstOrFail();

        $this->post('/text-to-text/glossary', [
            'term_source' => 'Bill',
            'term_target' => 'विधेयक',
            'language_pair' => 'en_to_hi',
        ])->assertRedirect(route('public.t2t'));

        $this->assertDatabaseHas('translator_glossary', [
            'term_source' => 'Bill',
            'term_target' => 'विधेयक',
        ]);

        $this->post('/text-to-text/assignments', [
            'slot_id' => $slot->id,
            'language_pair' => 'en_to_hi',
        ])->assertRedirect();

        $assignment = TranslatorAssignment::query()->where('slot_id', $slot->id)->firstOrFail();
        $block = $slot->blocks()->firstOrFail();

        $this->get(route('public.t2t', ['selected_assignment' => $assignment->id]))
            ->assertOk()
            ->assertSee('Progress')
            ->assertSee('Send to review')
            ->assertSee('विधेयक')
            ->assertSee('data-draft-key="public-t2t-'.$assignment->id.'-'.$block->id.'"', false);

        $this->post("/text-to-text/assignments/{$assignment->id}/translate", [
            'translations' => [$block->id => 'विधेयक विचार के लिए सूचीबद्ध है।'],
            'commit' => '1',
        ])->assertRedirect(route('public.t2t', ['selected_assignment' => $assignment->id]));

        $this->assertSame('in_review', $assignment->fresh()->status);
        $this->assertSame('विधेयक विचार के लिए सूचीबद्ध है।', $block->fresh()->translated_text);
        $this->assertGreaterThan(0, TranslatorGlossary::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.glossary.public_upserted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.assignment.public_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'translator.block.public_translated']);
        $this->assertAuditActionsChained('translator', [
            'translator.glossary.public_upserted',
            'translator.assignment.public_created',
            'translator.block.public_translated',
        ]);
    }
}
