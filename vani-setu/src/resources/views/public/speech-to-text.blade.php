@extends('layouts.public-design')

@section('content')
    <section class="rounded-lg bg-ink p-5 text-white shadow-base3 dark:bg-slate-950">
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
            <div>
                <span class="text-[10.5px] font-semibold uppercase tracking-wider text-white/70">{{ $section }}</span>
                <h2 class="mt-3 max-w-4xl font-display text-[30px] font-semibold leading-[36px] sm:text-[38px] sm:leading-[46px] md:text-[48px] md:leading-[58px]">{{ $heading }}</h2>
                <p class="mt-3 max-w-3xl text-sm leading-[1.65] text-white/75">{{ $summary }}</p>
            </div>
            <div class="rounded-lg border border-white/15 bg-white/10 p-5">
                <span class="text-[10.5px] font-semibold uppercase tracking-wider text-white/70">Active slot</span>
                <p class="mt-2 font-display text-3xl font-semibold text-gold sm:text-4xl">{{ $currentSlot?->code ?? 'Standby' }}</p>
                <p class="mt-2 text-sm leading-[1.65] text-white/70">{{ $currentSlot?->blocks->count() ?? 0 }} transcript blocks · {{ $currentSlot?->status ?? 'waiting for capture' }}</p>
            </div>
        </div>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[0.95fr_1.05fr]">
        <article class="{{ $designTokens['card'] }}">
            <div class="mb-4">
                <span class="{{ $designTokens['label'] }}">Capture intake</span>
                <h3 class="mt-1 text-xl font-medium text-slate-900 dark:text-slate-100">Create transcript block</h3>
            </div>
            <form method="POST" action="{{ route('public.s2t.captures.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="{{ $designTokens['label'] }}">Slot code</label>
                        <input name="slot_code" aria-label="Slot code" value="{{ old('slot_code', $currentSlot?->code) }}" placeholder="Auto if blank" class="mt-2 w-full rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="{{ $designTokens['label'] }}">Source language</label>
                        <select name="language" aria-label="Source language" class="mt-2 w-full rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            @foreach (['en' => 'English', 'hi' => 'Hindi', 'ta' => 'Tamil', 'ur' => 'Urdu', 'bn' => 'Bengali', 'mr' => 'Marathi'] as $code => $label)
                                <option value="{{ $code }}" @selected(old('language', 'en') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="{{ $designTokens['label'] }}">Proceeding topic</label>
                        <input name="topic" aria-label="Proceeding topic" value="{{ old('topic', $currentSlot?->topic ?? 'Live House Proceedings') }}" class="mt-2 w-full rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    </div>
                    <div class="md:col-span-2">
                        <label class="{{ $designTokens['label'] }}">Speaker label</label>
                        <input name="speaker_name" aria-label="Speaker label" value="{{ old('speaker_name') }}" placeholder="Optional public label" class="mt-2 w-full rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    </div>
                </div>
                <div>
                    <label class="{{ $designTokens['label'] }}">Captured transcript</label>
                    <textarea name="source_text" aria-label="Captured transcript" rows="6" class="mt-2 w-full rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" placeholder="Paste live ASR output or manually captured speech here.">{{ old('source_text') }}</textarea>
                </div>
                <div>
                    <label class="{{ $designTokens['label'] }}">Audio for ASR</label>
                    <div class="mt-2 flex flex-col gap-3 rounded-lg border border-dashed border-line bg-canvas p-3 dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex flex-wrap items-center gap-3">
                            <button id="s2tRecordToggle" type="button" class="rounded-full bg-ink px-4 py-2 text-xs font-semibold text-white">Record audio</button>
                            <span id="s2tRecordState" class="font-jbm text-xs text-slate-500 dark:text-slate-400">Upload or record audio for ASR</span>
                        </div>
                        <input id="s2tAudioInput" name="audio" type="file" accept="audio/*,video/webm,video/mp4" class="w-full text-sm text-slate-900 file:mr-3 file:rounded-full file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white dark:text-slate-100">
                        <audio id="s2tAudioPreview" class="hidden w-full" controls></audio>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">Upload audio when transcript text is unavailable. Pasted text stays authoritative if both are provided.</p>
                </div>
                <button class="rounded-full bg-ink px-4 py-2 text-xs font-semibold text-white" type="submit">Save capture block</button>
            </form>
        </article>

        <article class="{{ $designTokens['card'] }}">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="{{ $designTokens['label'] }}">Workflow stages</span>
                    <h3 class="mt-1 text-xl font-medium text-slate-900 dark:text-slate-100">Capture to handoff</h3>
                </div>
                @if ($currentSlot)
                    <form method="POST" action="{{ route('public.s2t.slots.handoff', $currentSlot) }}">
                        @csrf
                        <button class="rounded-full bg-gold px-4 py-2 text-xs font-semibold text-ink" type="submit">Hand off to T2T</button>
                    </form>
                @endif
            </div>
            <div class="grid gap-3 md:grid-cols-3">
                @foreach ($flowSteps as $step)
                    <div class="rounded-lg border border-line bg-canvas p-4 dark:border-slate-700 dark:bg-slate-900">
                        <span class="{{ $designTokens['label'] }}">{{ $step['name'] }}</span>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $step['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 rounded-lg border border-line p-4 dark:border-slate-700">
                <span class="{{ $designTokens['label'] }}">Current chamber slot</span>
                <div class="mt-2 grid gap-4 md:grid-cols-3">
                    <div><p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $currentSlot?->code ?? 'No active slot' }}</p><p class="text-xs text-slate-500">Code</p></div>
                    <div><p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $currentSlot?->status ?? 'offline' }}</p><p class="text-xs text-slate-500">Status</p></div>
                    <div><p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $currentSlot?->blocks->count() ?? 0 }}</p><p class="text-xs text-slate-500">Blocks</p></div>
                </div>
            </div>
        </article>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
        <article class="{{ $designTokens['card'] }}">
            <span class="{{ $designTokens['label'] }}">Correction desk</span>
            <h3 class="mt-1 text-xl font-medium text-slate-900 dark:text-slate-100">Review captured blocks</h3>
            <div class="mt-4 space-y-4">
                @forelse ($recentBlocks as $block)
                    <form method="POST" action="{{ route('public.s2t.blocks.update', $block) }}" class="rounded-lg border border-line p-4 dark:border-slate-700">
                        @csrf
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <span class="font-jbm text-xs text-slate-500">Block {{ $block->sequence }} · {{ $block->original_lang }} · v{{ $block->version }}</span>
                            <span class="rounded-full bg-statusBlue/10 px-2.5 py-1 font-jbm text-[11px] text-statusBlue">{{ $block->start_ms }}-{{ $block->end_ms }} ms</span>
                        </div>
                        <textarea name="text" aria-label="Corrected transcript block {{ $block->sequence }}" rows="4" class="w-full rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">{{ $block->text ?: $block->ai_text }}</textarea>
                        <button class="mt-3 rounded-full border border-line px-4 py-2 text-xs font-semibold text-ink dark:border-slate-700 dark:text-amber-400" type="submit">Save correction</button>
                    </form>
                @empty
                    <div class="rounded-lg border border-dashed border-line p-4 text-sm text-slate-500 dark:border-slate-700">No captured blocks are available yet.</div>
                @endforelse
            </div>
        </article>

        <article class="{{ $designTokens['card'] }}">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <span class="{{ $designTokens['label'] }}">Recent slots</span>
                    <h3 class="mt-1 text-xl font-medium text-slate-900 dark:text-slate-100">Production queue</h3>
                </div>
                <span class="rounded-[11px] bg-statusGreen/10 px-3 py-1 text-xs font-semibold text-statusGreen">{{ $auditSummary['count'] }} audit rows</span>
            </div>
            <div class="space-y-3">
                @foreach ($recentSlots as $slot)
                    <div class="rounded-lg border border-line p-4 dark:border-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ $slot->code }}</p>
                                <p class="text-xs text-slate-500">{{ $slot->topic }} · {{ $slot->blocks_count }} blocks</p>
                            </div>
                            <span class="rounded-[11px] bg-statusBlue/10 px-3 py-1 text-xs font-semibold text-statusBlue">{{ $slot->status }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
    <script>
        (() => {
            const toggle = document.getElementById('s2tRecordToggle');
            const input = document.getElementById('s2tAudioInput');
            const state = document.getElementById('s2tRecordState');
            const preview = document.getElementById('s2tAudioPreview');
            if (!toggle || !input || !navigator.mediaDevices || !window.MediaRecorder) {
                if (toggle) toggle.disabled = true;
                if (state) state.textContent = 'Audio upload available';
                return;
            }

            let recorder = null;
            let chunks = [];
            const preferredMime = () => ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/ogg']
                .find(type => window.MediaRecorder.isTypeSupported(type)) || '';
            const extensionFor = type => type.includes('mp4') ? 'm4a' : (type.includes('ogg') ? 'ogg' : 'webm');

            toggle.addEventListener('click', async () => {
                if (recorder && recorder.state === 'recording') {
                    recorder.stop();
                    toggle.textContent = 'Record audio';
                    if (state) state.textContent = 'Processing recording';
                    return;
                }

                try {
                    const stream = await navigator.mediaDevices.getUserMedia({audio: true});
                    chunks = [];
                    const mimeType = preferredMime();
                    recorder = mimeType ? new MediaRecorder(stream, {mimeType}) : new MediaRecorder(stream);
                    recorder.addEventListener('dataavailable', event => {
                        if (event.data.size > 0) chunks.push(event.data);
                    });
                    recorder.addEventListener('stop', () => {
                        stream.getTracks().forEach(track => track.stop());
                        const type = recorder.mimeType || mimeType || 'audio/webm';
                        const blob = new Blob(chunks, {type});
                        const file = new File([blob], `speech-to-text-capture.${extensionFor(type)}`, {type});
                        const transfer = new DataTransfer();
                        transfer.items.add(file);
                        input.files = transfer.files;
                        if (preview) {
                            preview.src = URL.createObjectURL(blob);
                            preview.classList.remove('hidden');
                        }
                        if (state) state.textContent = 'Recording ready for ASR';
                    });
                    recorder.start();
                    toggle.textContent = 'Stop recording';
                    if (state) state.textContent = 'Recording';
                } catch (error) {
                    if (state) state.textContent = 'Microphone blocked';
                }
            });
        })();
    </script>
@endsection
