<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $title }}</title>
<style>
  body { font-family: "DejaVu Sans", sans-serif; color: #111; font-size: 11pt; line-height: 1.45; }
  h1 { font-size: 18pt; margin: 0 0 4px 0; }
  .subtitle { color: #555; font-size: 10pt; margin-bottom: 18px; }
  .entry { margin-bottom: 14px; }
  .entry .meta { color: #888; font-size: 9pt; font-family: "DejaVu Sans Mono", monospace; }
  .entry .speaker { color: #d17a1f; font-weight: bold; }
  .entry .source { color: #000; margin-top: 2px; }
  .entry .translated { color: #2a7a40; font-style: italic; margin-top: 2px; }
  hr { border: 0; border-top: 1px solid #ddd; margin: 18px 0; }
</style>
</head>
<body>
  <h1>{{ $title }}</h1>
  <div class="subtitle">{{ $subtitle }}</div>
  <hr>
  @foreach ($entries as $entry)
    @php
      $ts = (string) ($entry['ts_label'] ?? '');
      $speaker = trim((string) ($entry['speaker'] ?? ''));
      $role = trim((string) ($entry['speaker_role'] ?? ''));
      $source = trim((string) ($entry['source'] ?? ''));
      $translated = trim((string) ($entry['translated'] ?? ''));
    @endphp
    <div class="entry">
      <div class="meta">
        @if ($ts !== '') [{{ $ts }}] @endif
        @if ($speaker !== '')
          <span class="speaker">{{ $speaker }}@if ($role !== '') · {{ $role }} @endif</span>
        @endif
      </div>
      @if ($source !== '') <div class="source">{{ $source }}</div> @endif
      @if ($translated !== '') <div class="translated">{{ $translated }}</div> @endif
    </div>
  @endforeach
</body>
</html>
