@php
    $path = request()->path();
    $workspace = match (true) {
        $path === 'app/login' => 'router',
        str_starts_with($path, 'app/translator') => 'translator',
        str_starts_with($path, 'app/reviewer') => 'reviewer',
        str_starts_with($path, 'app/sg') => 'sg',
        str_starts_with($path, 'app/director') => 'director',
        str_starts_with($path, 'app/synopsis') => 'synopsis',
        default => 'reporter',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ match ($workspace) {
        'router' => 'Vani Setu App Login',
        'translator' => 'M10 Translator Workspace',
        'reviewer' => 'M17 Reviewer Workspace',
        'sg' => 'M16 SG Workspace',
        'director' => 'M18 Director Workspace',
        'synopsis' => 'Synopsis Writer Workspace',
        default => 'M5 Reporter Workspace',
    } }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="m5-app">
    <div
        id="role-workspace"
        data-workspace="{{ $workspace }}"
        data-initial-slot="{{ request()->route('slot') }}"
        data-initial-assignment="{{ request()->route('assignment') }}"
        data-initial-window="{{ request()->route('window') }}"
        data-initial-job="{{ request()->route('job') }}"
        data-initial-consolidation="{{ request()->route('consolidation') }}"
    ></div>
</body>
</html>
