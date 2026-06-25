<?php

namespace App\Modules\SpeechToSpeech\Services;

use App\Modules\Core\Models\Member;
use App\Modules\SpeechToSpeech\Models\S2sGlossaryEntry;
use Illuminate\Support\Collection;

class S2sGlossaryService
{
    /** @var Collection<int, S2sGlossaryEntry>|null */
    private ?Collection $allCache = null;

    /**
     * @return Collection<int, S2sGlossaryEntry>
     */
    public function listAll(): Collection
    {
        // Request-scoped memo: the S2S pipeline calls listAll() at least
        // twice per chunk (translation + pronunciation overrides) — hitting
        // postgres for an almost-always-empty table on the hot path is
        // wasted RTT. Invalidated automatically because the service is
        // instantiated fresh per request by the Laravel container.
        if ($this->allCache !== null) {
            return $this->allCache;
        }
        return $this->allCache = S2sGlossaryEntry::query()
            ->orderBy('src_lang')
            ->orderBy('tgt_lang')
            ->orderBy('source_term')
            ->get();
    }

    /**
     * @return Collection<int, S2sGlossaryEntry>
     */
    public function listForPair(string $srcLang, string $tgtLang): Collection
    {
        return S2sGlossaryEntry::query()
            ->where('src_lang', $srcLang)
            ->where('tgt_lang', $tgtLang)
            ->orderBy('source_term')
            ->get();
    }

    /**
     * @param  array{src_lang:string, tgt_lang:string, source_term:string, target_term:string, pronunciation?:string|null, notes?:string|null}  $entry
     */
    public function upsert(array $entry): S2sGlossaryEntry
    {
        $values = [
            'target_term' => trim($entry['target_term']),
            'pronunciation' => trim((string) ($entry['pronunciation'] ?? '')),
            'notes' => trim((string) ($entry['notes'] ?? '')),
        ];

        S2sGlossaryEntry::query()->upsert(
            [[
                'src_lang' => trim($entry['src_lang']),
                'tgt_lang' => trim($entry['tgt_lang']),
                'source_term' => trim($entry['source_term']),
                ...$values,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['src_lang', 'tgt_lang', 'source_term'],
            ['target_term', 'pronunciation', 'notes', 'updated_at'],
        );

        $this->allCache = null;
        return S2sGlossaryEntry::query()
            ->where('src_lang', trim($entry['src_lang']))
            ->where('tgt_lang', trim($entry['tgt_lang']))
            ->where('source_term', trim($entry['source_term']))
            ->firstOrFail();
    }

    public function delete(int $id): bool
    {
        $this->allCache = null;
        return (bool) S2sGlossaryEntry::query()->whereKey($id)->delete();
    }

    /**
     * @param  iterable<S2sGlossaryEntry|array<string, mixed>>  $entries
     */
    public function applyTranslationOverrides(iterable $entries, string $srcLang, string $tgtLang, string $text): string
    {
        $result = $text;
        foreach ($this->ordered($entries) as $entry) {
            if (($entry['src_lang'] ?? null) !== $srcLang || ($entry['tgt_lang'] ?? null) !== $tgtLang) {
                continue;
            }

            $source = (string) ($entry['source_term'] ?? '');
            $target = (string) ($entry['target_term'] ?? '');
            if ($source === '' || $target === '') {
                continue;
            }

            $result = $this->replaceCaseInsensitive($result, $source, $target);
        }

        return $result;
    }

    /**
     * @param  iterable<S2sGlossaryEntry|array<string, mixed>>  $entries
     */
    public function applyPronunciationOverrides(iterable $entries, string $tgtLang, string $text): string
    {
        $result = $text;
        foreach ($this->ordered($entries) as $entry) {
            if (($entry['tgt_lang'] ?? null) !== $tgtLang) {
                continue;
            }

            $target = (string) ($entry['target_term'] ?? '');
            $pronunciation = (string) ($entry['pronunciation'] ?? '');
            if ($target === '' || $pronunciation === '' || $pronunciation === $target) {
                continue;
            }

            $result = $this->replaceCaseInsensitive($result, $target, $pronunciation);
        }

        return $result;
    }

    /**
     * @return array{added:int, skipped:int}
     */
    public function seedMembers(string $srcLang, string $tgtLang): array
    {
        $added = 0;
        $skipped = 0;

        Member::query()
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get(['name_en', 'name_hi'])
            ->each(function (Member $member) use ($srcLang, $tgtLang, &$added, &$skipped): void {
                $name = $this->memberNameForLanguage($member, $srcLang);
                if ($name === '') {
                    return;
                }

                $exists = S2sGlossaryEntry::query()
                    ->where('src_lang', $srcLang)
                    ->where('tgt_lang', $tgtLang)
                    ->where('source_term', $name)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    return;
                }

                S2sGlossaryEntry::query()->create([
                    'src_lang' => $srcLang,
                    'tgt_lang' => $tgtLang,
                    'source_term' => $name,
                    'target_term' => $name,
                    'pronunciation' => '',
                    'notes' => 'Seeded from member roster',
                ]);
                $added++;
            });

        return ['added' => $added, 'skipped' => $skipped];
    }

    private function replaceCaseInsensitive(string $text, string $needle, string $replacement): string
    {
        $quoted = preg_quote($needle, '/');
        $pattern = $this->isAsciiTerm($needle)
            ? '/\b'.$quoted.'\b/iu'
            : '/'.$quoted.'/iu';

        return preg_replace($pattern, $replacement, $text) ?? $text;
    }

    private function isAsciiTerm(string $term): bool
    {
        return preg_match('/^[\x00-\x7F]+$/', $term) === 1
            && preg_match('/[A-Za-z0-9]/', $term) === 1;
    }

    /**
     * @param  iterable<S2sGlossaryEntry|array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function ordered(iterable $entries): array
    {
        $items = [];
        foreach ($entries as $entry) {
            $items[] = $entry instanceof S2sGlossaryEntry ? $entry->toArray() : $entry;
        }

        usort($items, fn (array $a, array $b): int => strcmp((string) ($a['source_term'] ?? ''), (string) ($b['source_term'] ?? '')));

        return $items;
    }

    private function memberNameForLanguage(Member $member, string $languageCode): string
    {
        if (str_starts_with(strtolower($languageCode), 'hi')) {
            return trim((string) ($member->name_hi ?: $member->name_en));
        }

        return trim((string) $member->name_en);
    }
}
