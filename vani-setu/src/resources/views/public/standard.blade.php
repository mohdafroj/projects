@extends('layouts.public-design')

@section('content')
    <section class="rounded-lg bg-ink p-5 text-white shadow-base3 dark:bg-slate-950">
        <span class="text-[10.5px] font-semibold uppercase tracking-wider text-white/70">{{ $section }}</span>
        <h2 class="mt-3 font-display text-[40px] font-semibold leading-[48px] md:text-[48px] md:leading-[58px]">{{ $heading }}</h2>
        <p class="mt-3 max-w-4xl text-sm leading-[1.5] text-white/75">{{ $summary }}</p>
    </section>

    <section class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($principles as $principle)
            <article class="{{ $designTokens['card'] }}">
                <span class="{{ $designTokens['label'] }}">Principle {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $principle }}</p>
            </article>
        @endforeach
    </section>
@endsection
