@extends('layouts.public-design')

@section('content')
    @php
        $languagePairs = [
            'en_to_hi' => 'English to Hindi',
            'hi_to_en' => 'Hindi to English',
            'ta_to_hi' => 'Tamil to Hindi',
            'bn_to_hi' => 'Bengali to Hindi',
            'mr_to_hi' => 'Marathi to Hindi',
            'ur_to_hi' => 'Urdu to Hindi',
        ];
        $assignmentBlocks = $selectedAssignment?->blocks()->get() ?? collect();
        $translatedCount = $assignmentBlocks->filter(fn ($block) => filled($block->translated_text))->count();
        $progress = $assignmentBlocks->count() > 0 ? (int) round(($translatedCount / $assignmentBlocks->count()) * 100) : 0;
        $pairGlossary = $selectedAssignment
            ? $glossary->filter(fn ($term) => (string) $term->language_pair === (string) $selectedAssignment->language_pair)->values()
            : collect();
    @endphp

    <section class="rounded-lg bg-ink p-5 text-white shadow-base3 dark:bg-slate-950">
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
            <div>
                <span class="text-[10.5px] font-semibold uppercase tracking-wider text-white/70">{{ $section }}</span>
                <h2 class="mt-3 max-w-4xl font-display text-[30px] font-semibold leading-[36px] sm:text-[38px] sm:leading-[46px] md:text-[48px] md:leading-[58px]">{{ $heading }}</h2>
                <p class="mt-3 max-w-3xl text-sm leading-[1.65] text-white/75">{{ $summary }}</p>
            </div>
            <div class="rounded-lg border border-white/15 bg-white/10 p-5">
                <span class="text-[10.5px] font-semibold uppercase tracking-wider text-white/70">Selected assignment</span>
                <p class="mt-2 font-display text-3xl font-semibold text-gold sm:text-4xl">#{{ $selectedAssignment?->id ?? 'None' }}</p>
                <p class="mt-2 text-sm leading-[1.65] text-white/70">{{ $selectedAssignment?->language_pair ?? 'Open an assignment from a captured slot' }} · {{ $selectedAssignment?->status ?? 'waiting' }}</p>
            </div>
        </div>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
        <article class="{{ $designTokens['card'] }}">
            <div class="mb-4">
                <span class="{{ $designTokens['label'] }}">Assignment intake</span>
                <h3 class="mt-1 text-xl font-medium text-slate-900 dark:text-slate-100">Create translation task</h3>
            </div>
            <form method="POST" action="{{ route('public.t2t.assignments.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="{{ $designTokens['label'] }}">Captured slot</label>
                    <select name="slot_id" aria-label="Captured slot" class="mt-2 w-full rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        @forelse ($availableSlots as $slot)
                            <option value="{{ $slot->id }}" @selected($selectedAssignment?->slot_id === $slot->id)>{{ $slot->code }} · {{ $slot->topic }} · {{ $slot->blocks_count }} blocks</option>
                        @empty
                            <option value="">No captured slots available</option>
                        @endforelse
                    </select>
                </div>
                <div>
                    <label class="{{ $designTokens['label'] }}">Language pair</label>
                    <select name="language_pair" aria-label="Language pair" class="mt-2 w-full rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        @foreach ($languagePairs as $pair => $label)
                            <option value="{{ $pair }}" @selected($selectedAssignment?->language_pair === $pair)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="rounded-full bg-ink px-4 py-2 text-xs font-semibold text-white" type="submit">Open assignment</button>
            </form>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                @foreach ($flowSteps as $step)
                    <div class="rounded-lg border border-line bg-canvas p-4 dark:border-slate-700 dark:bg-slate-900">
                        <span class="{{ $designTokens['label'] }}">{{ $step['name'] }}</span>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $step['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="{{ $designTokens['card'] }}">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="{{ $designTokens['label'] }}">Translation workspace</span>
                    <h3 class="mt-1 text-xl font-medium text-slate-900 dark:text-slate-100">{{ $selectedAssignment?->slot?->code ?? 'No assignment selected' }}</h3>
                </div>
                <span class="rounded-[11px] bg-statusPurple/10 px-3 py-1 text-xs font-semibold text-statusPurple">{{ $selectedAssignment?->status ?? 'idle' }}</span>
            </div>

            @if ($selectedAssignment)
                <div class="mb-4 grid gap-3 md:grid-cols-4">
                    <div class="rounded-lg border border-line bg-canvas p-3 dark:border-slate-700 dark:bg-slate-900">
                        <span class="{{ $designTokens['label'] }}">Blocks</span>
                        <p class="mt-1 font-jbm text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $assignmentBlocks->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-line bg-canvas p-3 dark:border-slate-700 dark:bg-slate-900">
                        <span class="{{ $designTokens['label'] }}">Completed</span>
                        <p class="mt-1 font-jbm text-xl font-semibold text-statusGreen">{{ $translatedCount }}</p>
                    </div>
                    <div class="rounded-lg border border-line bg-canvas p-3 dark:border-slate-700 dark:bg-slate-900">
                        <span class="{{ $designTokens['label'] }}">Progress</span>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-white dark:bg-slate-800"><div class="h-full rounded-full bg-statusPurple" style="width: {{ $progress }}%"></div></div>
                        <p class="mt-1 font-jbm text-xs text-slate-500">{{ $progress }}%</p>
                    </div>
                    <div class="rounded-lg border border-line bg-canvas p-3 dark:border-slate-700 dark:bg-slate-900">
                        <span class="{{ $designTokens['label'] }}">Glossary</span>
                        <p class="mt-1 font-jbm text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $pairGlossary->count() }}</p>
                    </div>
                </div>
                @if ($pairGlossary->isNotEmpty())
                    <div class="mb-4 rounded-lg border border-statusPurple/20 bg-statusPurple/5 p-3">
                        <span class="{{ $designTokens['label'] }}">Active glossary</span>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($pairGlossary as $term)
                                <span class="rounded-full border border-statusPurple/20 bg-white px-2.5 py-1 text-xs font-semibold text-statusPurple dark:bg-slate-950">{{ $term->term_source }} → {{ $term->term_target }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('public.t2t.assignments.translate', $selectedAssignment) }}" class="space-y-4">
                    @csrf
                    @foreach ($assignmentBlocks as $block)
                        @php
                            $sourceText = (string) ($block->text ?: $block->ai_text);
                            $matches = $pairGlossary
                                ->filter(fn ($term) => str_contains(mb_strtolower($sourceText), mb_strtolower($term->term_source)))
                                ->take(4)
                                ->values();
                        @endphp
                        <div class="rounded-lg border border-line p-4 dark:border-slate-700">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-statusBlue/10 px-2.5 py-1 font-jbm text-[11px] text-statusBlue">Block {{ $block->sequence }}</span>
                                    <span class="rounded-full bg-canvas px-2.5 py-1 font-jbm text-[11px] text-slate-500 dark:bg-slate-900">{{ $block->start_ms }}-{{ $block->end_ms }} ms</span>
                                    <span class="rounded-full bg-canvas px-2.5 py-1 font-jbm text-[11px] text-slate-500 dark:bg-slate-900">{{ strtoupper($block->original_lang) }}</span>
                                </div>
                                <span class="rounded-full px-2.5 py-1 font-jbm text-[11px] {{ filled($block->translated_text) ? 'bg-statusGreen/10 text-statusGreen' : 'bg-statusAmber/10 text-statusAmber' }}">{{ filled($block->translated_text) ? 'translated' : 'draft needed' }}</span>
                            </div>
                            <div class="grid gap-4 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                                <div class="min-w-0">
                                    <span class="{{ $designTokens['label'] }}">Source</span>
                                    <p class="mt-2 whitespace-pre-wrap rounded-lg bg-canvas px-3 py-2 text-sm leading-6 text-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ $sourceText }}</p>
                                    @if ($matches->isNotEmpty())
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach ($matches as $term)
                                                <span class="rounded-full border border-statusPurple/20 bg-statusPurple/10 px-2.5 py-1 text-[11px] font-semibold text-statusPurple">{{ $term->term_source }} → {{ $term->term_target }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center justify-between gap-3">
                                        <label class="{{ $designTokens['label'] }}" for="translation-{{ $block->id }}">Reviewed translation</label>
                                        <span class="font-jbm text-[11px] text-slate-500" data-counter-for="translation-{{ $block->id }}">0 chars</span>
                                    </div>
                                    <textarea id="translation-{{ $block->id }}" name="translations[{{ $block->id }}]" data-draft-key="public-t2t-{{ $selectedAssignment->id }}-{{ $block->id }}" aria-label="Reviewed translation for block {{ $block->sequence }}" rows="5" class="t2t-translation mt-2 w-full resize-y rounded-lg border border-line bg-canvas px-3 py-2 text-sm leading-6 text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" placeholder="Leave blank to generate a glossary-aware draft.">{{ $block->translated_text }}</textarea>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    <div class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-surface/95 p-3 shadow-base backdrop-blur dark:border-slate-700 dark:bg-slate-950/95">
                        <label class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-200">
                            <input type="checkbox" name="commit" value="1" class="h-4 w-4 rounded border-line text-statusPurple">
                            Send to review
                        </label>
                        <button class="rounded-full bg-gold px-4 py-2 text-xs font-semibold text-ink" type="submit">Save translations</button>
                    </div>
                </form>
            @else
                <div class="rounded-lg border border-dashed border-line p-4 text-sm text-slate-500 dark:border-slate-700">Create an assignment from a captured slot to start translation.</div>
            @endif
        </article>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
        <article class="{{ $designTokens['card'] }}">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <span class="{{ $designTokens['label'] }}">Queue</span>
                    <h3 class="mt-1 text-xl font-medium text-slate-900 dark:text-slate-100">Assignments and review state</h3>
                </div>
                <span class="rounded-[11px] bg-statusPurple/10 px-3 py-1 text-xs font-semibold text-statusPurple">{{ $assignments->count() }} tracked</span>
            </div>
            <div class="space-y-3">
                @forelse ($assignments as $assignment)
                    <a href="{{ route('public.t2t', ['selected_assignment' => $assignment->id]) }}" class="block rounded-lg border p-4 transition hover:bg-canvas dark:hover:bg-slate-900 {{ $selectedAssignment?->id === $assignment->id ? 'border-statusPurple bg-statusPurple/5 dark:border-statusPurple' : 'border-line dark:border-slate-700' }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ $assignment->slot?->code ?? 'Slot pending' }} · {{ strtoupper(str_replace('_to_', ' to ', $assignment->language_pair)) }}</p>
                                <p class="text-xs text-slate-500">{{ $assignment->translator?->name ?? 'Public workflow user' }} · {{ $assignment->edits->count() }} edits</p>
                            </div>
                            <span class="rounded-[11px] bg-statusPurple/10 px-3 py-1 text-xs font-semibold text-statusPurple">{{ $assignment->status }}</span>
                        </div>
                    </a>
                @empty
                    <div class="rounded-lg border border-dashed border-line p-4 text-sm text-slate-500 dark:border-slate-700">No text-to-text assignments are available yet.</div>
                @endforelse
            </div>
        </article>

        <article class="{{ $designTokens['card'] }}">
            <div class="mb-4">
                <span class="{{ $designTokens['label'] }}">Glossary</span>
                <h3 class="mt-1 text-xl font-medium text-slate-900 dark:text-slate-100">Terminology control</h3>
            </div>
            <form method="POST" action="{{ route('public.t2t.glossary.store') }}" class="space-y-3">
                @csrf
                <div class="grid gap-3 md:grid-cols-2">
                    <input name="term_source" aria-label="Source glossary term" placeholder="Source term" class="rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    <input name="term_target" aria-label="Approved glossary target" placeholder="Approved target" class="rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                </div>
                <select name="language_pair" aria-label="Glossary language pair" class="w-full rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    @foreach ($languagePairs as $pair => $label)
                        <option value="{{ $pair }}">{{ $label }}</option>
                    @endforeach
                </select>
                <textarea name="notes" aria-label="Glossary usage note" rows="2" placeholder="Usage note" class="w-full rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"></textarea>
                <button class="rounded-full border border-line px-4 py-2 text-xs font-semibold text-ink dark:border-slate-700 dark:text-amber-400" type="submit">Save glossary term</button>
            </form>

            <div class="mt-5 space-y-2">
                @foreach ($glossary as $term)
                    <div class="rounded-lg bg-white px-3 py-2 text-sm dark:bg-slate-900">
                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ $term->term_source }}</span>
                        <span class="text-slate-600" aria-hidden="true">→</span>
                        <span class="text-slate-700 dark:text-slate-300">{{ $term->term_target }}</span>
                        <span class="ml-2 font-jbm text-[11px] text-slate-600">{{ $term->language_pair }}</span>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
    <script>
        (() => {
            const updateCounter = textarea => {
                const counter = document.querySelector(`[data-counter-for="${textarea.id}"]`);
                if (counter) counter.textContent = `${textarea.value.length} chars`;
                textarea.style.height = 'auto';
                textarea.style.height = `${Math.max(132, textarea.scrollHeight)}px`;
            };

            document.querySelectorAll('.t2t-translation').forEach(textarea => {
                const key = textarea.dataset.draftKey;
                const stored = key ? window.localStorage.getItem(key) : null;
                if (stored !== null && textarea.value.trim() === '') textarea.value = stored;
                updateCounter(textarea);
                textarea.addEventListener('input', () => {
                    if (key) window.localStorage.setItem(key, textarea.value);
                    updateCounter(textarea);
                });
                textarea.form?.addEventListener('submit', () => {
                    if (key) window.localStorage.removeItem(key);
                }, {once: true});
            });
        })();
    </script>
@endsection
