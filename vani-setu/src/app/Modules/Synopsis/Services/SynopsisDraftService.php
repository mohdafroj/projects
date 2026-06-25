<?php

namespace App\Modules\Synopsis\Services;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\Block;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SynopsisDraftService
{
    private const MAX_HOSTED_ATTRIBUTIONS = 100;
    private const EDITORIAL_STATUS = 'Editorial Status: First draft by AI; writer review and final commit required before publication.';

    /**
     * @return array{title:string, body:string, attributions:list<array<string, string|null>>, generation_meta:array<string, mixed>}
     */
    public function generate(ChiefConsolidation $consolidation): array
    {
        $consolidation->loadMissing('sitting');
        $blocks = $consolidation->blocks()->with(['member', 'customMember'])->orderBy('sequence')->get();
        $sourceHash = $this->blocksFingerprint($blocks);
        $items = $blocks->map(fn (Block $block): array => [
            'speaker_name' => $this->speakerName($block),
            'constituency' => $block->member?->state_jur ?? $block->customMember?->state_jur,
            'summary_text' => $this->summarise($block->text),
            'source_excerpt' => $this->excerpt($block->text),
        ]);
        $sitting = $consolidation->sitting;
        $title = "Synopsis - Sitting {$sitting->sitting_no} - Chunk {$consolidation->window_code}";
        $fallback = [
            'title' => $title,
            'body' => $this->renderBody($title, [
                'Session' => (string) $sitting->session_no,
                'Sitting' => (string) $sitting->sitting_no,
                'Chunk' => $consolidation->window_code,
                'Duration' => $this->durationLabel($consolidation->duration_ms),
                'Source' => 'Approved Chief consolidation',
                'Generation Model' => $this->modelLabel(),
            ], $items),
            'attributions' => $this->attributions($items),
            'generation_meta' => $this->generationMeta('fallback', null, 'endpoint_not_configured'),
            'source_sha256' => $sourceHash,
        ];

        return $this->hostedDraft($consolidation, $title, 'Approved Chief consolidation', $items, $fallback, null, $sourceHash);
    }

    /**
     * @return array{title:string, body:string, attributions:list<array<string, string|null>>, generation_meta:array<string, mixed>}
     */
    public function generateFromText(ChiefConsolidation $consolidation, string $sourceText, ?string $title = null): array
    {
        $consolidation->loadMissing('sitting');
        $sourceHash = hash('sha256', $sourceText);
        $items = $this->itemsFromText($sourceText);
        $sitting = $consolidation->sitting;
        $title = $title ?: "Synopsis - Sitting {$sitting->sitting_no} - Chunk {$consolidation->window_code}";
        $fallback = [
            'title' => $title,
            'body' => $this->renderBody($title, [
                'Session' => (string) $sitting->session_no,
                'Sitting' => (string) $sitting->sitting_no,
                'Chunk' => $consolidation->window_code,
                'Duration' => $this->durationLabel($consolidation->duration_ms),
                'Source' => 'Writer pasted proceedings text',
                'Generation Model' => $this->modelLabel(),
            ], $items),
            'attributions' => $this->attributions($items),
            'generation_meta' => $this->generationMeta('fallback', null, 'endpoint_not_configured'),
            'source_sha256' => $sourceHash,
        ];

        return $this->hostedDraft($consolidation, $title, 'Writer pasted proceedings text', $items, $fallback, $sourceText, $sourceHash);
    }

    private function speakerName(Block $block): string
    {
        return $block->member?->name_en ?? $block->customMember?->name_en ?? 'Proceedings';
    }

    private function summarise(string $text): string
    {
        $normalised = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if ($normalised === '') {
            return 'Proceedings were taken up.';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $normalised, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $summary = trim(implode(' ', array_slice($sentences, 0, 2))) ?: $normalised;

        return mb_strlen($summary) > 260 ? mb_substr($summary, 0, 257).'...' : $summary;
    }

    /**
     * @param  Collection<int, array{speaker_name:string, constituency:?string, summary_text:string, source_excerpt:string}>  $items
     * @param  array{title:string, body:string, attributions:list<array<string, string|null>>, generation_meta:array<string, mixed>}  $fallback
     * @return array{title:string, body:string, attributions:list<array<string, string|null>>, generation_meta:array<string, mixed>}
     */
    private function hostedDraft(ChiefConsolidation $consolidation, string $title, string $sourceLabel, Collection $items, array $fallback, ?string $sourceText = null, ?string $sourceHash = null): array
    {
        $url = trim((string) config('services.synopsis.model_url', ''));
        if ($url === '') {
            return $fallback;
        }

        if (! $this->isSupportedHostedEndpoint($url)) {
            return $this->fallbackWithReason($fallback, 'invalid_hosted_endpoint');
        }

        if ($this->isForbiddenHostedEndpoint($url)) {
            return $this->fallbackWithReason($fallback, 'forbidden_sarvam_endpoint');
        }

        if (! $this->isAllowedHostedEndpoint($url)) {
            return $this->fallbackWithReason($fallback, 'non_inhouse_hosted_endpoint');
        }

        $requestId = (string) Str::uuid();
        $request = Http::timeout((int) config('services.synopsis.timeout', 20))
            ->retry(
                (int) config('services.synopsis.retries', 1),
                (int) config('services.synopsis.retry_sleep_ms', 250),
            )
            ->withHeaders([
                'X-Vani-Setu-Module' => 'synopsis',
                'X-Vani-Setu-Chunk' => (string) $consolidation->window_code,
                'X-Vani-Setu-Model' => $this->modelLabel(),
                'X-Vani-Setu-Source-Sha256' => (string) $sourceHash,
                'X-Vani-Setu-Request-Id' => $requestId,
            ]);

        $token = trim((string) config('services.synopsis.token', ''));
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post($url, [
                'request_id' => $requestId,
                'model' => $this->modelLabel(),
                'task' => 'parliamentary_synopsis',
                'title' => $title,
                'source' => [
                    'label' => $sourceLabel,
                    'session_no' => $consolidation->sitting?->session_no,
                    'sitting_no' => $consolidation->sitting?->sitting_no,
                    'chunk_code' => $consolidation->window_code,
                    'duration_ms' => $consolidation->duration_ms,
                    'text' => $sourceText,
                    'sha256' => $sourceHash,
                    'items' => $items->values()->all(),
                ],
                'template' => [
                    'required_sections' => ['Source Notes', 'Synopsis', 'Attribution Notes', 'Editorial Status'],
                    'style' => 'formal_parliamentary_record',
                    'editorial_status' => str_replace('Editorial Status: ', '', self::EDITORIAL_STATUS),
                ],
            ]);

            if (! $response->successful()) {
                return $this->fallbackWithReason($fallback, 'hosted_model_http_error', $response->status(), null, $requestId);
            }

            $payload = $response->json();

            return is_array($payload)
                ? $this->normaliseHostedPayload($payload, $fallback, $response->status(), $requestId, (string) $sourceHash, $sourceLabel)
                : $this->fallbackWithReason($fallback, 'hosted_model_invalid_json', $response->status(), null, $requestId);
        } catch (\Throwable $exception) {
            return $this->fallbackWithReason($fallback, 'hosted_model_exception', null, $exception->getMessage(), $requestId);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{title:string, body:string, attributions:list<array<string, string|null>>, generation_meta:array<string, mixed>}  $fallback
     * @return array{title:string, body:string, attributions:list<array<string, string|null>>, generation_meta:array<string, mixed>}
     */
    private function normaliseHostedPayload(array $payload, array $fallback, int $status, string $requestId, string $expectedSourceHash, string $expectedSourceLabel): array
    {
        $titleValue = $payload['title'] ?? '';
        if (! is_string($titleValue)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_invalid_title', $status, null, $requestId);
        }

        $title = trim($titleValue);
        if ($title === '') {
            return $this->fallbackWithReason($fallback, 'hosted_model_missing_title', $status, null, $requestId);
        }

        if (mb_strlen($title) > 255) {
            return $this->fallbackWithReason($fallback, 'hosted_model_title_too_long', $status, null, $requestId);
        }

        if (! array_key_exists('body', $payload)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_missing_body', $status, null, $requestId);
        }

        $bodyValue = $payload['body'];
        if (! is_string($bodyValue)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_invalid_body', $status, null, $requestId);
        }

        $body = trim($bodyValue);
        if ($body === '') {
            return $this->fallbackWithReason($fallback, 'hosted_model_empty_body', $status, null, $requestId);
        }

        if (mb_strlen($body) > 50000) {
            return $this->fallbackWithReason($fallback, 'hosted_model_body_too_long', $status, null, $requestId);
        }

        $requiredSections = ['Source Notes', 'Synopsis', 'Attribution Notes', 'Editorial Status'];
        $sectionPositions = [];
        foreach ($requiredSections as $section) {
            $position = $this->hostedSectionHeadingPosition($body, $section);

            if ($position === null) {
                return $this->fallbackWithReason($fallback, 'hosted_model_missing_section', $status, null, $requestId);
            }

            $sectionPositions[] = $position;
        }

        if (! $this->hostedSectionsAreInOrder($sectionPositions)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_invalid_section_order', $status, null, $requestId);
        }

        if (! $this->hostedSourceNotesMatchExpected($body, $expectedSourceLabel)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_source_notes_mismatch', $status, null, $requestId);
        }

        if (! $this->hostedEditorialStatusIsValid($body)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_invalid_editorial_status', $status, null, $requestId);
        }

        if (! hash_equals($title, $this->hostedBodyTitle($body))) {
            return $this->fallbackWithReason($fallback, 'hosted_model_title_body_mismatch', $status, null, $requestId);
        }

        $responseSourceHashValue = $this->hostedAliasValue($payload, 'source_sha256', 'source.sha256');
        if (! is_string($responseSourceHashValue)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_invalid_source_hash', $status, null, $requestId);
        }

        $responseSourceHash = trim($responseSourceHashValue);
        if ($responseSourceHash === '' || ! hash_equals($expectedSourceHash, $responseSourceHash)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_source_mismatch', $status, null, $requestId);
        }

        $rawAttributions = $payload['attributions'] ?? null;
        if (is_array($rawAttributions) && ! array_is_list($rawAttributions)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_invalid_attributions_shape', $status, null, $requestId);
        }

        if (is_array($rawAttributions) && count($rawAttributions) > self::MAX_HOSTED_ATTRIBUTIONS) {
            return $this->fallbackWithReason($fallback, 'hosted_model_too_many_attributions', $status, null, $requestId);
        }

        if (! $this->hostedAttributionRowsAreValid($rawAttributions)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_invalid_attribution_row', $status, null, $requestId);
        }

        if (! $this->hostedAttributionLengthsAreValid($rawAttributions)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_attribution_too_long', $status, null, $requestId);
        }

        $attributions = $this->normaliseHostedAttributions($rawAttributions);
        if ($attributions === []) {
            return $this->fallbackWithReason($fallback, 'hosted_model_missing_attributions', $status, null, $requestId);
        }

        if (! $this->hostedAttributionNotesMatchRows($body, $attributions)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_attribution_notes_mismatch', $status, null, $requestId);
        }

        if (! $this->hostedSynopsisBulletsMatchRows($body, $attributions)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_synopsis_notes_mismatch', $status, null, $requestId);
        }

        $responseRequestIdPresent = array_key_exists('request_id', $payload) || Arr::has($payload, 'meta.request_id');
        if (! $responseRequestIdPresent) {
            return $this->fallbackWithReason($fallback, 'hosted_model_missing_request_id', $status, null, $requestId);
        }

        $responseRequestIdValue = $this->hostedAliasValue($payload, 'request_id', 'meta.request_id');
        if (! is_string($responseRequestIdValue)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_invalid_request_id', $status, null, $requestId);
        }

        $responseRequestId = trim($responseRequestIdValue);
        if ($responseRequestId === '' || ! hash_equals($requestId, $responseRequestId)) {
            return $this->fallbackWithReason($fallback, 'hosted_model_request_mismatch', $status, null, $requestId);
        }

        return [
            'title' => $title,
            'body' => $body,
            'attributions' => $attributions,
            'generation_meta' => $this->generationMeta('hosted_model', $status, null, null, $requestId),
            'source_sha256' => $fallback['source_sha256'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, array{speaker_name:string, constituency:?string, summary_text:string, source_excerpt:string}>  $items
     */
    private function renderBody(string $title, array $metadata, Collection $items): string
    {
        $lines = [
            $title,
            '',
            'Source Notes',
        ];

        foreach ($metadata as $label => $value) {
            $lines[] = "{$label}: {$value}";
        }

        $lines[] = '';
        $lines[] = 'Synopsis';

        foreach ($items as $item) {
            $label = $item['constituency']
                ? "{$item['speaker_name']} ({$item['constituency']})"
                : $item['speaker_name'];
            $lines[] = "- {$label}: {$item['summary_text']}";
        }

        $lines[] = '';
        $lines[] = 'Attribution Notes';

        foreach ($items as $item) {
            $label = $item['constituency']
                ? "{$item['speaker_name']} ({$item['constituency']})"
                : $item['speaker_name'];
            $lines[] = "- {$label}: source excerpt - {$item['source_excerpt']}";
        }

        $lines[] = '';
        $lines[] = self::EDITORIAL_STATUS;

        return implode("\n", $lines);
    }

    /**
     * @return Collection<int, array{speaker_name:string, constituency:?string, summary_text:string, source_excerpt:string}>
     */
    private function itemsFromText(string $sourceText): Collection
    {
        $paragraphs = collect(preg_split('/\R{2,}/u', trim($sourceText), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->values();

        if ($paragraphs->isEmpty()) {
            return collect([[
                'speaker_name' => 'Proceedings',
                'constituency' => null,
                'summary_text' => 'Proceedings were taken up.',
                'source_excerpt' => 'No source excerpt supplied.',
            ]]);
        }

        return $paragraphs
            ->chunk(2)
            ->take(8)
            ->values()
            ->map(function (Collection $chunk): array {
                $text = $chunk->implode(' ');
                [$speaker, $constituency, $body] = $this->splitSpeakerLine($text);

                return [
                    'speaker_name' => $speaker,
                    'constituency' => $constituency,
                    'summary_text' => $this->summarise($body),
                    'source_excerpt' => $this->excerpt($body),
                ];
            });
    }

    /**
     * @return array{0:string, 1:?string, 2:string}
     */
    private function splitSpeakerLine(string $text): array
    {
        if (preg_match('/^\s*(?<speaker>[^:(\n]{2,120})(?:\s+\((?<constituency>[^)]{2,120})\))?\s*:\s*(?<body>.+)$/us', $text, $matches)) {
            return [
                Str::headline(trim($matches['speaker'])),
                isset($matches['constituency']) ? trim($matches['constituency']) : null,
                trim($matches['body']),
            ];
        }

        return ['Proceedings', null, $text];
    }

    private function excerpt(string $text): string
    {
        $normalised = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        return mb_strlen($normalised) > 180 ? mb_substr($normalised, 0, 177).'...' : ($normalised ?: 'No source excerpt supplied.');
    }

    /**
     * @param  Collection<int, array{speaker_name:string, constituency:?string, summary_text:string, source_excerpt:string}>  $items
     * @return list<array<string, string|null>>
     */
    private function attributions(Collection $items): array
    {
        return $items->map(fn (array $item): array => [
            'speaker_name' => $item['speaker_name'],
            'constituency' => $item['constituency'],
            'summary_text' => $item['summary_text'],
        ])->values()->all();
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function normaliseHostedAttributions(mixed $attributions): array
    {
        if (! is_array($attributions)) {
            return [];
        }

        if (! array_is_list($attributions)) {
            return [];
        }

        return collect($attributions)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $speakerValue = data_get($item, 'speaker_name');
                $summaryValue = data_get($item, 'summary_text');

                if (! is_string($speakerValue) || ! is_string($summaryValue)) {
                    return null;
                }

                $speaker = trim($speakerValue);
                $summary = trim($summaryValue);

                if ($speaker === '' || $summary === '') {
                    return null;
                }

                $constituencyValue = data_get($item, 'constituency');
                $constituency = is_string($constituencyValue) ? trim($constituencyValue) : '';

                return [
                    'speaker_name' => $speaker,
                    'constituency' => $constituency !== '' ? $constituency : null,
                    'summary_text' => $summary,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function hostedAttributionLengthsAreValid(mixed $attributions): bool
    {
        if (! is_array($attributions)) {
            return true;
        }

        if (! array_is_list($attributions)) {
            return false;
        }

        foreach ($attributions as $item) {
            if (! is_array($item)) {
                return false;
            }

            $speaker = data_get($item, 'speaker_name');
            $constituency = data_get($item, 'constituency');
            $summary = data_get($item, 'summary_text');

            if (
                ! is_string($speaker)
                || ! is_string($summary)
                || (! is_null($constituency) && ! is_string($constituency))
            ) {
                return false;
            }

            if (
                mb_strlen(trim($speaker)) > 255
                || mb_strlen(trim((string) $constituency)) > 255
                || mb_strlen(trim($summary)) > 2000
            ) {
                return false;
            }
        }

        return true;
    }

    private function hostedAttributionRowsAreValid(mixed $attributions): bool
    {
        if (! is_array($attributions)) {
            return true;
        }

        if (! array_is_list($attributions)) {
            return false;
        }

        foreach ($attributions as $item) {
            if (! is_array($item)) {
                return false;
            }

            $speaker = data_get($item, 'speaker_name');
            $constituency = data_get($item, 'constituency');
            $summary = data_get($item, 'summary_text');

            if (
                ! is_string($speaker)
                || ! is_string($summary)
                || (! is_null($constituency) && ! is_string($constituency))
                || trim($speaker) === ''
                || trim($summary) === ''
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, string|null>>  $attributions
     */
    private function hostedSynopsisBulletsMatchRows(string $body, array $attributions): bool
    {
        $synopsis = $this->hostedSectionBody($body, 'Synopsis', 'Attribution Notes');

        if ($synopsis === '') {
            return false;
        }

        return $this->hostedSectionBulletLabelsMatchRows($synopsis, $attributions);
    }

    /**
     * @param  list<array<string, string|null>>  $attributions
     */
    private function hostedAttributionNotesMatchRows(string $body, array $attributions): bool
    {
        $notes = $this->hostedSectionBody($body, 'Attribution Notes', 'Editorial Status');

        if ($notes === '') {
            return false;
        }

        return $this->hostedSectionBulletLabelsMatchRows($notes, $attributions);
    }

    /**
     * @param  list<array<string, string|null>>  $attributions
     */
    private function hostedSectionBulletLabelsMatchRows(string $sectionBody, array $attributions): bool
    {
        $labels = [];
        foreach ($attributions as $attribution) {
            $speaker = trim((string) ($attribution['speaker_name'] ?? ''));
            $constituency = trim((string) ($attribution['constituency'] ?? ''));
            $label = $constituency !== '' ? "{$speaker} ({$constituency})" : $speaker;

            if ($speaker === '') {
                return false;
            }

            $labels[] = $label;
        }

        $bulletLabels = $this->hostedSectionBulletLabels($sectionBody);
        if ($bulletLabels === null) {
            return false;
        }

        sort($labels);
        sort($bulletLabels);

        return $labels === $bulletLabels;
    }

    /**
     * @return list<string>|null
     */
    private function hostedSectionBulletLabels(string $sectionBody): ?array
    {
        $labels = [];
        $lines = preg_split('/\R/u', $sectionBody, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (! str_starts_with($line, '- ')) {
                continue;
            }

            $content = trim(mb_substr($line, 2));
            if (! str_contains($content, ':')) {
                return null;
            }

            $label = trim(Str::before($content, ':'));
            $text = trim(Str::after($content, ':'));
            if ($label === '' || $text === '') {
                return null;
            }

            $labels[] = $label;
        }

        return $labels;
    }

    private function hostedBodyTitle(string $body): string
    {
        $lines = preg_split('/\R/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line !== '') {
                return $line;
            }
        }

        return '';
    }

    private function hostedSourceNotesMatchExpected(string $body, string $expectedSourceLabel): bool
    {
        $sourceNotes = $this->hostedSectionBody($body, 'Source Notes', 'Synopsis');
        if ($sourceNotes === '') {
            return false;
        }

        $expected = 'Source: '.trim($expectedSourceLabel);
        $lines = preg_split('/\R/u', $sourceNotes, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sourceLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'Source:')) {
                $sourceLines[] = $line;
            }
        }

        return count($sourceLines) === 1 && hash_equals($expected, $sourceLines[0]);
    }

    private function hostedEditorialStatusIsValid(string $body): bool
    {
        $expected = preg_quote(self::EDITORIAL_STATUS, '/');

        return preg_match("/(^|\\R){$expected}\\s*$/u", $body) === 1;
    }

    private function hostedSectionBody(string $body, string $section, string $nextSection): string
    {
        $sectionPosition = $this->hostedSectionHeadingPosition($body, $section);
        $nextPosition = $this->hostedSectionHeadingPosition($body, $nextSection);

        if ($sectionPosition === null || $nextPosition === null || $nextPosition <= $sectionPosition) {
            return '';
        }

        return trim(mb_substr($body, $sectionPosition, $nextPosition - $sectionPosition));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hostedAliasValue(array $payload, string $primaryKey, string $aliasKey): mixed
    {
        $hasPrimary = array_key_exists($primaryKey, $payload);
        $hasAlias = Arr::has($payload, $aliasKey);

        if (! $hasPrimary) {
            return $hasAlias ? data_get($payload, $aliasKey) : '';
        }

        $primary = $payload[$primaryKey];
        if (! $hasAlias) {
            return $primary;
        }

        $alias = data_get($payload, $aliasKey);
        if (! is_string($primary) || ! is_string($alias)) {
            return null;
        }

        return hash_equals(trim($primary), trim($alias)) ? $primary : null;
    }

    private function hostedSectionHeadingPosition(string $body, string $section): ?int
    {
        $quotedSection = preg_quote($section, '/');
        $pattern = $section === 'Editorial Status'
            ? "/(^|\\R)({$quotedSection}\\s*:)/u"
            : "/(^|\\R)({$quotedSection}\\s*:?\\s*)(?=\\R|$)/u";

        if (! preg_match($pattern, $body, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        return $matches[2][1];
    }

    /**
     * @param  list<int>  $positions
     */
    private function hostedSectionsAreInOrder(array $positions): bool
    {
        $offset = -1;

        foreach ($positions as $position) {
            if ($position <= $offset) {
                return false;
            }

            $offset = $position;
        }

        return true;
    }

    /**
     * @param  Collection<int, Block>  $blocks
     */
    private function blocksFingerprint(Collection $blocks): string
    {
        $payload = $blocks->map(fn (Block $block): array => [
            'id' => $block->id,
            'sequence' => $block->sequence,
            'version' => $block->version,
            'member_id' => $block->member_id,
            'custom_member_id' => $block->custom_member_id,
            'text_sha256' => hash('sha256', (string) $block->text),
        ])->values()->all();

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function durationLabel(int $durationMs): string
    {
        return max(1, (int) round($durationMs / 60000)).' minutes';
    }

    private function modelLabel(): string
    {
        return (string) config('services.synopsis.model', 'vani-setu-synopsis');
    }

    private function isForbiddenHostedEndpoint(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && str_ends_with(strtolower($host), 'sarvam.ai');
    }

    private function isSupportedHostedEndpoint(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($scheme)
            && is_string($host)
            && in_array(strtolower($scheme), ['http', 'https'], true)
            && trim($host) !== '';
    }

    private function isAllowedHostedEndpoint(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host)) {
            return false;
        }

        $allowedHosts = config('services.synopsis.allowed_hosts', []);
        if (! is_array($allowedHosts) || $allowedHosts === []) {
            return false;
        }

        $host = strtolower(trim($host));

        foreach ($allowedHosts as $allowedHost) {
            $allowedHost = strtolower(trim((string) $allowedHost));
            if ($allowedHost !== '' && hash_equals($allowedHost, $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function generationMeta(string $provider, ?int $status = null, ?string $fallbackReason = null, ?string $fallbackDetail = null, ?string $requestId = null): array
    {
        return [
            'provider' => $provider,
            'model' => $this->modelLabel(),
            'endpoint_configured' => trim((string) config('services.synopsis.model_url', '')) !== '',
            'http_status' => $status,
            'fallback_reason' => $fallbackReason,
            'fallback_detail' => $fallbackDetail,
            'request_id' => $requestId,
        ];
    }

    /**
     * @param  array{title:string, body:string, attributions:list<array<string, string|null>>, generation_meta:array<string, mixed>}  $fallback
     * @return array{title:string, body:string, attributions:list<array<string, string|null>>, generation_meta:array<string, mixed>}
     */
    private function fallbackWithReason(array $fallback, string $reason, ?int $status = null, ?string $detail = null, ?string $requestId = null): array
    {
        $fallback['generation_meta'] = [
            ...$this->generationMeta('fallback', $status, $reason, $detail ? mb_substr($detail, 0, 240) : null, $requestId),
        ];

        return $fallback;
    }
}
