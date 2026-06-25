@extends('layouts.public-design')

@section('content')
    <section class="rounded-lg bg-ink p-5 text-white shadow-base3 dark:bg-slate-950">
        <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_340px]">
            <div>
                <span class="text-[10.5px] font-semibold uppercase tracking-wider text-white/70">{{ $section }}</span>
                <h2 class="mt-3 max-w-3xl font-display text-[30px] font-semibold leading-[36px] sm:text-[36px] sm:leading-[42px] md:text-[48px] md:leading-[58px]">{{ $heading }}</h2>
                <p class="mt-2 text-sm font-semibold uppercase tracking-[0.24em] text-gold/90">{{ $brandTagline }}</p>
                <p class="mt-3 max-w-3xl text-sm leading-[1.65] text-white/75">{{ $summary }}</p>
            </div>
            <div class="rounded-lg border border-white/15 bg-white/10 p-5">
                <span class="text-[10.5px] font-semibold uppercase tracking-wider text-white/70">Production console</span>
                <p class="mt-2 font-display text-3xl font-semibold text-gold sm:text-4xl">3 Workflows</p>
                <p class="mt-2 text-sm leading-[1.65] text-white/70">Speech-to-speech, speech-to-text, and text-to-text each open into an actionable workflow, not a design reference page.</p>
            </div>
        </div>
    </section>

    <section class="mt-5 grid gap-4 xl:grid-cols-3">
        @foreach ($modalityCards as $card)
            <a href="{{ $card['href'] }}" class="{{ $designTokens['card'] }} transition hover:-translate-y-0.5 hover:shadow-base2">
                <div class="flex items-start justify-between gap-3">
                    <span class="{{ $designTokens['label'] }}">{{ $card['eyebrow'] }}</span>
                    <span class="rounded-[11px] px-3 py-1 text-xs font-semibold {{ $card['status_color'] }}">{{ $card['status'] }}</span>
                </div>
                <h3 class="mt-3 text-xl font-medium text-slate-900 dark:text-slate-100">{{ $card['title'] }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $card['summary'] }}</p>
                <p class="mt-4 font-jbm text-xs text-slate-500">Open surface →</p>
            </a>
        @endforeach
    </section>

    <section class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($metrics as $metric)
            <article class="{{ $designTokens['card'] }}">
                <span class="{{ $designTokens['label'] }}">{{ $metric['label'] }}</span>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="{{ $designTokens['value'] }}">{{ $metric['value'] }}</span>
                    <span class="font-jbm tabular-nums text-sm {{ $metric['status'] }}">{{ $metric['delta'] }}</span>
                </div>
                <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $metric['meta'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[1fr_340px]">
        <div class="{{ $designTokens['card'] }}">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="{{ $designTokens['label'] }}">Workflow map</span>
                <h3 class="mt-1 text-xl font-medium text-slate-900 dark:text-slate-100">Production service workflows</h3>
                </div>
                <span class="rounded-[11px] bg-statusGreen/10 px-3 py-1 text-xs font-semibold text-statusGreen">Operational</span>
            </div>

            <div class="divide-y divide-line dark:divide-slate-700">
                @foreach ($workflows as $workflow)
                    <div class="grid grid-cols-1 gap-3 py-4 md:grid-cols-[180px_120px_1fr] md:items-center">
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $workflow['name'] }}</span>
                        <span class="w-fit rounded-[11px] px-3 py-1 text-xs font-semibold {{ $workflow['color'] }}">{{ $workflow['status'] }}</span>
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ $workflow['detail'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <aside class="{{ $designTokens['card'] }}">
            <span class="{{ $designTokens['label'] }}">Workflow readiness</span>
            <h3 class="mt-1 text-xl font-medium text-slate-900 dark:text-slate-100">Operational path</h3>
            <ul class="mt-4 space-y-3 text-sm leading-6">
                <li class="flex gap-3"><span class="font-jbm text-statusGreen">01</span><span>Speech-to-speech opens sessions, accepts audio/text segments, and displays translated channel outputs.</span></li>
                <li class="flex gap-3"><span class="font-jbm text-statusGreen">02</span><span>Speech-to-text creates live slots, stores transcript blocks, supports correction, and hands off to translation.</span></li>
                <li class="flex gap-3"><span class="font-jbm text-statusGreen">03</span><span>Text-to-text creates assignments, applies glossary terms, stores reviewed translations, and updates audit logs.</span></li>
                <li class="flex gap-3"><span class="font-jbm text-statusGreen">04</span><span>Primary navigation contains only the production workflows and dashboard.</span></li>
            </ul>
        </aside>
    </section>
@endsection
