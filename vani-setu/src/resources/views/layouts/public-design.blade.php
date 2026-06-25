<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle ?? 'Vani Setu' }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Newsreader:opsz,wght@6..72,400;600&family=Noto+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        @endif
    </head>
    <body class="font-sans antialiased text-slate-500 dark:text-slate-300">
        <div class="flex min-h-screen bg-canvas dark:bg-slate-900">
            <aside class="fixed left-0 top-0 hidden h-full w-[248px] border-r border-line bg-surface px-5 py-5 shadow-base dark:border-slate-700 dark:bg-slate-800 lg:block">
                <a href="{{ route('public.home') }}" class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-ink text-sm font-semibold text-white">VS</span>
                    <span>
                        <span class="block font-display text-xl font-semibold leading-6 text-slate-900 dark:text-slate-100">Vani Setu</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $brandTagline }}</span>
                    </span>
                </a>

                <nav class="mt-8 space-y-1">
                    @foreach ($navigation as $item)
                        <a href="{{ $item['href'] }}" class="flex items-center justify-between rounded px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-canvas hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white">
                            <span>{{ $item['label'] }}</span>
                            <span class="font-jbm text-[10px] text-slate-500" aria-hidden="true">→</span>
                        </a>
                    @endforeach
                </nav>

                <div class="mt-8 rounded-lg border border-line bg-gold-bg p-4 text-xs leading-5 text-slate-700">
                    <span class="block font-semibold text-slate-900">Production surface</span>
                    <span class="mt-1 block">Speech-to-speech, speech-to-text, and text-to-text workflows are available from this public console.</span>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col lg:ml-[248px]">
                <header class="sticky top-0 z-50 border-b border-line bg-surface/95 px-[15px] py-3 backdrop-blur dark:border-slate-700 dark:bg-slate-800/95 md:px-6">
                    <div class="flex flex-col gap-3 sm:min-h-[60px] sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="mb-2 flex items-center gap-3 lg:hidden">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ink text-sm font-semibold text-white">VS</span>
                                <div class="min-w-0">
                                    <span class="block font-display text-lg font-semibold leading-5 text-slate-900 dark:text-slate-100">Vani Setu</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $brandTagline }}</span>
                                </div>
                            </div>
                            <span class="{{ $designTokens['label'] }}">{{ $section ?? 'Public' }}</span>
                            <h1 class="text-base font-semibold leading-5 text-slate-900 dark:text-slate-100">{{ $pageTitle ?? 'Vani Setu' }}</h1>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $brandTagline }}</p>
                        </div>
                        <a href="{{ route('public.s2s') }}" class="w-fit rounded-full border border-line px-4 py-2 text-xs font-semibold text-ink transition hover:bg-ink hover:text-white dark:border-slate-700 dark:text-amber-400">
                            Speech To Speech
                        </a>
                    </div>
                </header>

                <main class="flex-1 px-[15px] pb-24 pt-[15px] md:px-6 md:pb-[37px] md:pt-6">
                    <div class="mx-auto max-w-screen-2xl">
                        @if (session('status'))
                            <section class="mb-5 rounded-lg border border-statusGreen/20 bg-statusGreen/10 p-4 text-sm font-medium text-statusGreen">
                                {{ session('status') }}
                            </section>
                        @endif
                        @if (isset($errors) && $errors->any())
                            <section class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                                {{ $errors->first() }}
                            </section>
                        @endif
                        @yield('content')
                    </div>
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
