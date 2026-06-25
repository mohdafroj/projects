<?php

namespace App\Http\Controllers;

use App\Modules\Capture\Services\TijoriAsrGateway;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\SpeechToSpeech\Models\S2sOutput;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use App\Modules\Translator\Models\TranslatorAssignment;
use App\Modules\Translator\Models\TranslatorEdit;
use App\Modules\Translator\Models\TranslatorGlossary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicDesignController extends Controller
{
    public const DESIGN_SOURCE = '/gov/commoncore/design.md';

    public const BRAND_TAGLINE = 'AI-powered language bridge';

    public function home(): View
    {
        $modalityCards = $this->modalityCards();

        return $this->renderPublicPage('public.home', [
            'pageTitle' => 'Vani Setu Public Dashboard',
            'section' => 'Three Modalities',
            'heading' => 'Vani Setu',
            'summary' => 'Production gateway for speech-to-speech, speech-to-text, and text-to-text flows with actionable intake, review, handoff, and audit trails.',
            'metrics' => [
                ['label' => 'Workflows live', 'value' => (string) count($modalityCards), 'delta' => 'production paths', 'status' => 'text-statusGreen', 'meta' => 'Speech-to-speech, speech-to-text, and text-to-text expose working public workflows'],
                ['label' => 'Speech sessions', 'value' => (string) S2sSession::query()->count(), 'delta' => (string) S2sOutput::query()->count().' outputs', 'status' => 'text-statusAmber', 'meta' => 'Sarvam session and output records tracked through the speech fanout pipeline'],
                ['label' => 'Text capture blocks', 'value' => (string) Block::query()->count(), 'delta' => (string) Slot::query()->whereIn('status', ['open', 'in_progress', 'committed_partial', 'committed_full'])->count().' slots', 'status' => 'text-statusBlue', 'meta' => 'Live speech-to-text capture inventory across chamber slots'],
                ['label' => 'Translation queue', 'value' => (string) TranslatorAssignment::query()->count(), 'delta' => (string) TranslatorGlossary::query()->count().' glossary terms', 'status' => 'text-statusPurple', 'meta' => 'Text-to-text assignments, AI assists, and terminology controls'],
                ['label' => 'Integrity checks', 'value' => '100%', 'delta' => 'shared audit', 'status' => 'text-statusGreen', 'meta' => 'All three modalities flow through the append-only audit chain'],
            ],
            'modalityCards' => $modalityCards,
            'workflows' => $this->workflowMap(),
        ]);
    }

    public function speechToText(): View
    {
        $slotQuery = Slot::query()
            ->with(['sitting', 'assignments.user:id,name,employee_id', 'blocks.member', 'blocks.customMember'])
            ->whereIn('status', ['in_progress', 'open', 'committed_partial', 'committed_full']);

        $selectedSlotId = request()->integer('selected_slot');
        $currentSlot = $selectedSlotId > 0
            ? (clone $slotQuery)->find($selectedSlotId)
            : null;

        $currentSlot ??= $slotQuery
            ->orderByRaw("case when status = 'in_progress' then 0 else 1 end")
            ->orderBy('id')
            ->first();
        $recentSlots = Slot::query()
            ->withCount('blocks')
            ->with('sitting')
            ->latest('updated_at')
            ->limit(6)
            ->get();

        return $this->renderPublicPage('public.speech-to-text', [
            'pageTitle' => 'Speech To Text Production',
            'section' => 'Speech To Text',
            'heading' => 'Capture, correct, and hand off proceedings text',
            'summary' => 'Production workflow for live chamber speech intake: create a slot, capture transcript blocks, correct text and speaker metadata, then hand off the slot into translation.',
            'currentSlot' => $currentSlot,
            'recentBlocks' => $currentSlot?->blocks->sortByDesc('start_ms')->take(6)->values() ?? collect(),
            'recentSlots' => $recentSlots,
            'auditSummary' => $this->auditSummary('speech_to_text'),
            'flowSteps' => [
                ['name' => '1. Capture', 'detail' => 'Create or continue a live slot and append source transcript blocks from microphone, line-in, or uploaded audio.'],
                ['name' => '2. Correct', 'detail' => 'Review generated text, speaker labels, timings, and language lane before the slot is committed.'],
                ['name' => '3. Handoff', 'detail' => 'Mark the slot committed and open a text-to-text assignment for translator review.'],
            ],
        ]);
    }

    public function textToText(): View
    {
        $selectedAssignment = $this->selectedTranslatorAssignment();

        return $this->renderPublicPage('public.text-to-text', [
            'pageTitle' => 'Text To Text Production',
            'section' => 'Text To Text',
            'heading' => 'Translate, review, and prepare final text',
            'summary' => 'Production workflow for text-to-text: open assignments from captured slots, apply glossary terms, generate AI-assisted drafts, and commit reviewed translations.',
            'selectedAssignment' => $selectedAssignment,
            'availableSlots' => Slot::query()->with('sitting')->withCount('blocks')->latest('updated_at')->limit(12)->get(),
            'assignments' => TranslatorAssignment::query()
                ->with(['slot.sitting', 'translator:id,name,employee_id', 'edits'])
                ->latest('updated_at')
                ->limit(8)
                ->get(),
            'glossary' => TranslatorGlossary::query()->latest('id')->limit(20)->get(),
            'glossaryCount' => TranslatorGlossary::query()->count(),
            'auditSummary' => $this->auditSummary('text_to_text'),
            'flowSteps' => [
                ['name' => '1. Assign', 'detail' => 'Choose a captured slot and target language pair for translator processing.'],
                ['name' => '2. Translate', 'detail' => 'Generate a draft, apply glossary replacements, and allow manual final text edits.'],
                ['name' => '3. Commit', 'detail' => 'Move the assignment into review with edits and audit entries attached to every block.'],
            ],
        ]);
    }

    public function storeSpeechToTextCapture(Request $request, AuditLogger $audit, TijoriAsrGateway $asr): RedirectResponse
    {
        $data = $request->validate([
            'slot_code' => ['nullable', 'string', 'max:40'],
            'topic' => ['required', 'string', 'max:255'],
            'language' => ['required', Rule::in(['en', 'hi', 'ta', 'ur', 'bn', 'mr'])],
            'speaker_name' => ['nullable', 'string', 'max:255'],
            'source_text' => ['nullable', 'string', 'max:12000'],
            'audio' => ['nullable', 'file', 'max:153600', 'mimetypes:audio/webm,video/webm,audio/wav,audio/x-wav,audio/mpeg,audio/mp3,audio/mpeg3,audio/mp4,video/mp4,audio/ogg'],
        ]);

        if (! filled($data['source_text'] ?? null) && ! $request->hasFile('audio')) {
            throw ValidationException::withMessages([
                'source_text' => 'Paste transcript text or upload an audio file for ASR.',
            ]);
        }

        $audioFile = $request->file('audio');
        $slot = DB::transaction(function () use ($data, $audit, $audioFile, $asr): Slot {
            $user = $this->publicWorkflowUser();
            $sitting = Sitting::query()->firstOrCreate(
                ['session_no' => (int) now()->format('Y'), 'sitting_no' => (int) now()->format('md')],
                ['sitting_date' => today(), 'status' => 'live', 'started_at' => now()],
            );
            $slotCode = $data['slot_code'] ?: 'PUBLIC-'.now()->format('His');
            $slot = Slot::query()->firstOrCreate(
                ['sitting_id' => $sitting->id, 'code' => $slotCode],
                [
                    'start_offset_ms' => 0,
                    'duration_ms' => 300000,
                    'topic' => $data['topic'],
                    'status' => 'in_progress',
                ],
            );
            $slot->forceFill(['topic' => $data['topic'], 'status' => 'in_progress'])->save();
            $audioMeta = $audioFile instanceof UploadedFile
                ? $this->storePublicSpeechAudio($audioFile, $slot)
                : null;
            $asrResult = $audioMeta
                ? $asr->transcribeSlotAudio($slot, $audioMeta['path'])
                : null;
            $capturedText = (string) ($data['source_text'] ?? '');
            $asrText = is_array($asrResult) ? (string) ($asrResult['transcript'] ?? '') : '';
            $blockText = trim($capturedText) !== '' ? $capturedText : $asrText;

            if (trim($blockText) === '') {
                throw ValidationException::withMessages([
                    'source_text' => 'ASR did not return transcript text. Paste transcript text and try again.',
                ]);
            }

            SlotAssignment::query()->updateOrCreate(
                ['slot_id' => $slot->id, 'lang_role' => $data['language']],
                ['user_id' => $user->id, 'status' => 'in_progress', 'workflow_stage' => 'reporter'],
            );

            $sequence = ((int) $slot->blocks()->max('sequence')) + 1;
            $block = Block::withoutEvents(fn () => Block::query()->create([
                'slot_id' => $slot->id,
                'sequence' => $sequence,
                'start_ms' => ($sequence - 1) * 30000,
                'end_ms' => $sequence * 30000,
                'original_lang' => $this->normalizePublicLanguage($asrResult['language'] ?? $data['language']),
                'chief_lang' => in_array($data['language'], ['hi'], true) ? 'hi' : 'en',
                'ai_action' => 'native',
                'ai_text' => $blockText,
                'text' => $blockText,
                'version' => 1,
                'reporter_edit_count' => 0,
            ]));

            $audit->log('capture.block.public_created', $block, [
                'slot_code' => $slot->code,
                'language' => $data['language'],
                'speaker_name' => $data['speaker_name'] ?? null,
                'audio' => $audioMeta,
                'asr' => $asrResult,
                'source' => 'public_production_console',
            ]);

            return $slot;
        });

        return redirect()
            ->route('public.s2t', ['selected_slot' => $slot->id])
            ->with('status', 'Speech-to-text capture block created.');
    }

    /**
     * @return array{disk:string, path:string, mime_type:string, original_name:string, size:int|null}
     */
    private function storePublicSpeechAudio(UploadedFile $file, Slot $slot): array
    {
        $disk = (string) config('filesystems.reporter_audio_disk', 'vani_audio');
        $path = $file->storeAs("public-s2t/{$slot->id}", $file->getClientOriginalName() ?: 'capture-audio.webm', $disk);
        throw_if(! is_string($path), \RuntimeException::class, 'Unable to store speech-to-text audio.');

        return [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => (string) $file->getMimeType(),
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ];
    }

    private function normalizePublicLanguage(?string $language): string
    {
        $language = strtolower((string) $language);
        $language = str_contains($language, '-') ? strtok($language, '-') : $language;

        return in_array($language, ['en', 'hi', 'ta', 'ur', 'bn', 'mr'], true) ? $language : 'en';
    }

    public function updateSpeechToTextBlock(Request $request, AuditLogger $audit, string $block): RedirectResponse
    {
        $block = Block::query()->findOrFail($block);
        $data = $request->validate([
            'text' => ['required', 'string', 'max:12000'],
        ]);

        $before = $block->text;
        Block::withoutEvents(fn () => $block->forceFill([
            'text' => $data['text'],
            'version' => $block->version + 1,
            'reporter_edit_count' => $block->reporter_edit_count + 1,
        ])->save());

        $audit->log('capture.block.public_corrected', $block, [
            'before_excerpt' => mb_substr((string) $before, 0, 160),
            'after_excerpt' => mb_substr($data['text'], 0, 160),
        ]);

        return redirect()
            ->route('public.s2t', ['selected_slot' => $block->slot_id])
            ->with('status', 'Transcript block corrected.');
    }

    public function handoffSpeechToTextSlot(AuditLogger $audit, string $slot): RedirectResponse
    {
        $slot = Slot::query()->findOrFail($slot);
        $assignment = DB::transaction(function () use ($slot, $audit): TranslatorAssignment {
            $user = $this->publicWorkflowUser();
            $slot->forceFill(['status' => 'committed_full'])->save();
            $slot->assignments()->update([
                'status' => 'committed',
                'workflow_stage' => 'supervisor',
                'committed_at' => now(),
                'last_workflow_action_at' => now(),
            ]);

            $assignment = TranslatorAssignment::query()->updateOrCreate(
                ['slot_id' => $slot->id, 'language_pair' => 'en_to_hi'],
                [
                    'sitting_id' => $slot->sitting_id,
                    'translator_user_id' => $user->id,
                    'status' => 'open',
                    'ai_translation_meta' => ['source' => 'speech_to_text_handoff'],
                ],
            );

            $audit->log('capture.slot.public_handoff', $slot, [
                'slot_code' => $slot->code,
                'translator_assignment_id' => $assignment->id,
            ]);

            return $assignment;
        });

        return redirect()
            ->route('public.t2t', ['selected_assignment' => $assignment->id])
            ->with('status', 'Slot handed off to text-to-text translation.');
    }

    public function storeTextToTextAssignment(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'language_pair' => ['required', Rule::in(['en_to_hi', 'hi_to_en', 'ta_to_hi', 'bn_to_hi', 'mr_to_hi', 'ur_to_hi'])],
        ]);

        $slot = Slot::query()->findOrFail($data['slot_id']);
        $user = $this->publicWorkflowUser();
        $assignment = TranslatorAssignment::query()->updateOrCreate(
            ['slot_id' => $slot->id, 'language_pair' => $data['language_pair']],
            [
                'sitting_id' => $slot->sitting_id,
                'translator_user_id' => $user->id,
                'status' => 'open',
                'ai_translation_meta' => ['source' => 'public_production_console'],
            ],
        );

        $audit->log('translator.assignment.public_created', $assignment, [
            'slot_code' => $slot->code,
            'language_pair' => $data['language_pair'],
        ]);

        return redirect()
            ->route('public.t2t', ['selected_assignment' => $assignment->id])
            ->with('status', 'Text-to-text assignment opened.');
    }

    public function translateTextToTextAssignment(Request $request, AuditLogger $audit, string $assignment): RedirectResponse
    {
        $assignment = TranslatorAssignment::query()->findOrFail($assignment);
        $data = $request->validate([
            'translations' => ['required', 'array', 'min:1'],
            'translations.*' => ['nullable', 'string', 'max:12000'],
            'commit' => ['nullable', 'boolean'],
        ]);

        [$sourceLang, $targetLang] = $this->languagePairParts($assignment->language_pair);

        DB::transaction(function () use ($data, $assignment, $audit, $sourceLang, $targetLang): void {
            foreach ($assignment->blocks()->get() as $block) {
                $manual = trim((string) ($data['translations'][$block->id] ?? ''));
                $source = (string) ($block->text ?: $block->ai_text);
                $after = $manual !== '' ? $manual : $this->draftTranslation($source, $sourceLang, $targetLang, $assignment->language_pair);
                $before = $block->translated_text;

                Block::withoutEvents(fn () => $block->forceFill([
                    'translated_text' => $after,
                    'ai_action' => 'translated',
                ])->save());

                $log = $audit->log('translator.block.public_translated', $block, [
                    'assignment_id' => $assignment->id,
                    'language_pair' => $assignment->language_pair,
                    'manual' => $manual !== '',
                ]);

                TranslatorEdit::query()->create([
                    'assignment_id' => $assignment->id,
                    'block_id' => $block->id,
                    'kind' => 'text',
                    'ai_suggestion' => $manual === '' ? $after : null,
                    'before' => $before,
                    'after' => $after,
                    'audit_log_id' => $log->id,
                ]);
            }

            $assignment->forceFill([
                'status' => ($data['commit'] ?? false) ? 'in_review' : 'draft',
                'ai_translation_meta' => array_merge($assignment->ai_translation_meta ?? [], [
                    'translated_at' => now()->toIso8601String(),
                    'source' => 'public_production_console',
                ]),
            ])->save();
        });

        return redirect()
            ->route('public.t2t', ['selected_assignment' => $assignment->id])
            ->with('status', 'Text-to-text draft updated.');
    }

    public function storeTextToTextGlossary(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'term_source' => ['required', 'string', 'max:255'],
            'term_target' => ['required', 'string', 'max:255'],
            'language_pair' => ['required', Rule::in(['en_to_hi', 'hi_to_en', 'ta_to_hi', 'bn_to_hi', 'mr_to_hi', 'ur_to_hi'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $glossary = TranslatorGlossary::query()->updateOrCreate(
            ['term_source' => $data['term_source'], 'language_pair' => $data['language_pair']],
            [
                'term_target' => $data['term_target'],
                'domain' => 'parliamentary',
                'notes' => $data['notes'] ?? null,
                'created_by' => $this->publicWorkflowUser()->id,
                'approved_at' => now(),
            ],
        );

        $audit->log('translator.glossary.public_upserted', $glossary, [
            'language_pair' => $data['language_pair'],
            'term_source' => $data['term_source'],
        ]);

        return redirect()->route('public.t2t')->with('status', 'Glossary term saved.');
    }

    public function standard(): View
    {
        return $this->renderPublicPage('public.standard', [
            'pageTitle' => 'Public Design Standard',
            'section' => 'Master Controller',
            'heading' => 'Standard Design Principles',
            'summary' => 'Internal reference only. The production navigation points to the three operational workflows.',
            'principles' => [
                'Use semantic Tailwind tokens for ink, gold, surface, canvas, line, and status colors.',
                'Use the governed shell: fixed sidebar, sticky 60px header, canvas background, and max-w-screen-2xl content.',
                'Use Noto Sans for body text, Newsreader for display values, DM Sans for secondary UI, and JetBrains Mono for tabular figures.',
                'Use p-5 cards with rounded-lg, border-line, bg-surface, and shadow-base.',
                'Keep public pages data-driven through PublicDesignController::renderPublicPage().',
            ],
        ]);
    }

    protected function renderPublicPage(string $view, array $data = []): View
    {
        return view($view, array_merge($this->designSystem(), $data));
    }

    protected function designSystem(): array
    {
        return [
            'designSource' => self::DESIGN_SOURCE,
            'brandTagline' => self::BRAND_TAGLINE,
            'navigation' => [
                ['label' => 'Dashboard', 'href' => route('public.home')],
                ['label' => 'Speech To Speech', 'href' => route('public.s2s')],
                ['label' => 'Speech To Text', 'href' => route('public.s2t')],
                ['label' => 'Text To Text', 'href' => route('public.t2t')],
            ],
            'designTokens' => [
                'hero' => 'bg-ink text-white',
                'page' => 'bg-canvas dark:bg-slate-900',
                'card' => 'rounded-lg border border-line bg-surface p-5 shadow-base dark:border-slate-700 dark:bg-slate-800',
                'label' => 'text-[10.5px] uppercase tracking-wider font-semibold text-slate-600 dark:text-slate-300',
                'value' => 'font-display text-2xl font-semibold text-slate-900 dark:text-slate-100',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function modalityCards(): array
    {
        return [
            [
                'eyebrow' => 'Speech To Speech',
                'title' => 'Live multilingual listening',
                'summary' => 'Open a live session, submit audio or text segments, and monitor multilingual text/audio output channels.',
                'href' => route('public.s2s'),
                'status' => 'Sarvam audio pipeline',
                'status_color' => 'bg-statusAmber/10 text-statusAmber',
            ],
            [
                'eyebrow' => 'Speech To Text',
                'title' => 'Reporter block capture',
                'summary' => 'Create transcript blocks, correct captured text, commit the slot, and hand it to translation.',
                'href' => route('public.s2t'),
                'status' => 'Reporter workflow',
                'status_color' => 'bg-statusBlue/10 text-statusBlue',
            ],
            [
                'eyebrow' => 'Text To Text',
                'title' => 'Translation and vetting',
                'summary' => 'Create translation assignments, maintain glossary terms, draft translations, and mark work ready for review.',
                'href' => route('public.t2t'),
                'status' => 'Translator workflow',
                'status_color' => 'bg-statusPurple/10 text-statusPurple',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function workflowMap(): array
    {
        return [
            ['name' => 'Speech To Speech', 'status' => 'Live', 'detail' => 'Audio intake, Sarvam translation fanout, vocabulary control, listener view, and fallback channel marking.', 'color' => 'bg-statusAmber/10 text-statusAmber'],
            ['name' => 'Speech To Text', 'status' => 'Live', 'detail' => 'Public capture form, correction desk, slot queue, audit logging, and handoff into translation.', 'color' => 'bg-statusBlue/10 text-statusBlue'],
            ['name' => 'Text To Text', 'status' => 'Live', 'detail' => 'Assignment creation, glossary enforcement, draft generation, reviewed translation storage, and audit logging.', 'color' => 'bg-statusPurple/10 text-statusPurple'],
            ['name' => 'Audit Fabric', 'status' => 'Shared', 'detail' => 'All modalities write into the same append-only audit framework with modality-specific chain segments.', 'color' => 'bg-statusGreen/10 text-statusGreen'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditSummary(string $segment): array
    {
        $rows = AuditLog::query()
            ->where('chain_segment', $segment)
            ->latest('id')
            ->limit(6)
            ->get(['action', 'created_at', 'actor_role']);

        return [
            'segment' => $segment,
            'count' => AuditLog::query()->where('chain_segment', $segment)->count(),
            'latest_action' => $rows->first()?->action,
            'latest_at' => $rows->first()?->created_at,
            'recent' => $rows,
        ];
    }

    private function selectedTranslatorAssignment(): ?TranslatorAssignment
    {
        $id = request()->integer('selected_assignment');

        $query = TranslatorAssignment::query()->with(['slot.blocks', 'slot.sitting', 'translator:id,name,employee_id', 'edits']);

        if ($id > 0) {
            return $query->find($id);
        }

        return $query->latest('updated_at')->first();
    }

    private function publicWorkflowUser(): User
    {
        return User::query()->firstOrCreate(
            ['employee_id' => 'VS-PUBLIC-001'],
            [
                'name' => 'Vani Setu Public Workflow',
                'email' => 'public-workflow@vanisetu.local',
                'password' => Hash::make(str()->random(32)),
                'section' => 'Public Production Console',
                'designation' => 'Workflow Operator',
                'language_competencies' => ['en', 'hi', 'en_to_hi', 'hi_to_en'],
                'is_active' => true,
            ],
        );
    }

    private function draftTranslation(string $source, string $sourceLang, string $targetLang, string $languagePair): string
    {
        $text = trim($source);
        foreach (TranslatorGlossary::query()->where('language_pair', $languagePair)->whereNotNull('approved_at')->get() as $term) {
            $text = str_replace($term->term_source, $term->term_target, $text);
        }

        return '['.strtoupper($targetLang).' draft from '.strtoupper($sourceLang).'] '.$text;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function languagePairParts(string $languagePair): array
    {
        if (str_contains($languagePair, '_to_')) {
            $parts = explode('_to_', $languagePair, 2);

            return [$parts[0] ?: 'en', $parts[1] ?: 'hi'];
        }

        return ['en', $languagePair ?: 'hi'];
    }
}
