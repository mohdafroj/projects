<?php

namespace App\Modules\Search\Services;

use App\Modules\Core\Models\Block;
use App\Modules\Search\Models\StoredArtifact;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;

class SearchIndexer
{
    private const INDEX = 'blocks';
    private const ARTIFACT_INDEX = 'artifacts';

    public function __construct(private readonly HttpFactory $http)
    {
    }

    public function indexBlock(Block $block): void
    {
        $this->ensureSettings();

        $this->http->withHeaders($this->headers())
            ->post($this->baseUrl().'/indexes/'.self::INDEX.'/documents', [
                $this->documentFor($block->loadMissing(['slot.sitting', 'slot.assignments', 'member', 'customMember'])),
            ])
            ->throw();
    }

    public function indexArtifact(StoredArtifact $artifact): void
    {
        $this->ensureArtifactSettings();

        $this->http->withHeaders($this->headers())
            ->post($this->baseUrl().'/indexes/'.self::ARTIFACT_INDEX.'/documents', [
                $this->artifactDocumentFor($artifact),
            ])
            ->throw();
    }

    public function reindexAll(int $chunkSize = 250): int
    {
        $this->ensureSettings();
        $count = 0;

        Block::query()
            ->with(['slot.sitting', 'slot.assignments', 'member', 'customMember'])
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $blocks) use (&$count) {
                $documents = $blocks->map(fn (Block $block) => $this->documentFor($block))->values()->all();

                if ($documents !== []) {
                    $this->http->withHeaders($this->headers())
                        ->post($this->baseUrl().'/indexes/'.self::INDEX.'/documents', $documents)
                        ->throw();
                }

                $count += count($documents);
            });

        return $count;
    }

    public function reindexArtifacts(int $chunkSize = 250): int
    {
        $this->ensureArtifactSettings();
        $count = 0;

        StoredArtifact::query()
            ->where('search_eligible', true)
            ->where('sensitivity_classification', 'non_sensitive')
            ->whereNotNull('search_text')
            ->orderBy('id')
            ->chunkById(max(1, $chunkSize), function (Collection $artifacts) use (&$count) {
                $documents = $artifacts->map(fn (StoredArtifact $artifact) => $this->artifactDocumentFor($artifact))->values()->all();

                if ($documents !== []) {
                    $this->http->withHeaders($this->headers())
                        ->post($this->baseUrl().'/indexes/'.self::ARTIFACT_INDEX.'/documents', $documents)
                        ->throw();
                }

                $count += count($documents);
            });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function search(string $query, array $filters = [], int $page = 1): array
    {
        $this->ensureSettings();

        $response = $this->http->withHeaders($this->headers())
            ->post($this->baseUrl().'/indexes/'.self::INDEX.'/search', [
                'q' => $query,
                'page' => max(1, $page),
                'hitsPerPage' => 15,
                'attributesToHighlight' => ['text', 'ai_text', 'translated_text', 'member_name_en', 'member_name_hi'],
                'highlightPreTag' => '<mark>',
                'highlightPostTag' => '</mark>',
                'filter' => $this->filterExpression($filters),
                'sort' => ['sitting_date:desc', 'slot_code:asc'],
            ])
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function searchArtifacts(string $query, array $filters = [], int $page = 1): array
    {
        $this->ensureArtifactSettings();

        $response = $this->http->withHeaders($this->headers())
            ->post($this->baseUrl().'/indexes/'.self::ARTIFACT_INDEX.'/search', [
                'q' => $query,
                'page' => max(1, $page),
                'hitsPerPage' => 15,
                'attributesToHighlight' => ['title', 'search_text', 'metadata_text', 'tags'],
                'highlightPreTag' => '<mark>',
                'highlightPostTag' => '</mark>',
                'filter' => $this->artifactFilterExpression($filters),
                'sort' => ['created_at:desc'],
            ])
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    public function ensureSettings(): void
    {
        $settings = [
            'searchableAttributes' => ['text', 'ai_text', 'translated_text', 'member_name_en', 'member_name_hi'],
            'filterableAttributes' => ['original_lang', 'workflow_stage', 'sitting_date', 'slot_code', 'source_type', 'committee_id', 'in_camera_flag'],
            'sortableAttributes' => ['sitting_date', 'slot_code'],
        ];

        $this->http->withHeaders($this->headers())
            ->patch($this->baseUrl().'/indexes/'.self::INDEX.'/settings', $settings)
            ->throw();
    }

    public function ensureArtifactSettings(): void
    {
        $settings = [
            'searchableAttributes' => ['title', 'search_text', 'metadata_text', 'tags', 'source_module'],
            'filterableAttributes' => ['media_family', 'source_module', 'mime_type', 'extension', 'tags', 'subject_type', 'subject_id'],
            'sortableAttributes' => ['created_at', 'indexed_at'],
        ];

        $this->http->withHeaders($this->headers())
            ->patch($this->baseUrl().'/indexes/'.self::ARTIFACT_INDEX.'/settings', $settings)
            ->throw();
    }

    /**
     * @return array<string, mixed>
     */
    private function documentFor(Block $block): array
    {
        $member = $block->member ?: $block->customMember;
        $slot = $block->slot;
        $sitting = $slot?->sitting;

        return [
            'id' => (string) $block->id,
            'slot_id' => $block->slot_id,
            'sitting_id' => $slot?->sitting_id,
            'text' => $block->text,
            'ai_text' => $block->ai_text,
            'translated_text' => $block->translated_text,
            'original_lang' => $block->original_lang,
            'chief_lang' => $block->chief_lang,
            'member_name_en' => $member?->name_en,
            'member_name_hi' => $member?->name_hi,
            'slot_code' => $slot?->code,
            'sitting_date' => $sitting?->sitting_date?->toDateString(),
            'workflow_stage' => $slot?->overallWorkflowStage() ?? 'reporter',
            'source_type' => $block->getAttribute('source_type') ?? 'house',
            'committee_id' => $block->getAttribute('committee_id'),
            'in_camera_flag' => (bool) $block->getAttribute('in_camera_flag'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function artifactDocumentFor(StoredArtifact $artifact): array
    {
        return [
            'id' => (string) $artifact->id,
            'uuid' => $artifact->uuid,
            'title' => $artifact->title,
            'storage_uri' => $artifact->storage_uri,
            'mime_type' => $artifact->mime_type,
            'extension' => $artifact->extension,
            'media_family' => $artifact->media_family,
            'source_system' => $artifact->source_system,
            'source_module' => $artifact->source_module,
            'subject_type' => $artifact->subject_type,
            'subject_id' => $artifact->subject_id,
            'tags' => $artifact->tags ?? [],
            'metadata_text' => $artifact->metadata_text,
            'search_text' => $artifact->search_text,
            'created_at' => $artifact->created_at?->toIso8601String(),
            'indexed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filterExpression(array $filters): ?string
    {
        $clauses = [];

        foreach (['original_lang', 'workflow_stage', 'slot_code'] as $field) {
            if (! empty($filters[$field]) && is_string($filters[$field])) {
                $clauses[] = $field.' = '.$this->quoted($filters[$field]);
            }
        }

        if (! array_key_exists('include_in_camera', $filters) || ! $filters['include_in_camera']) {
            $clauses[] = 'in_camera_flag = false';
        }

        if (! empty($filters['source_type']) && is_string($filters['source_type'])) {
            $clauses[] = 'source_type = '.$this->quoted($filters['source_type']);
        }

        if (! empty($filters['committee_id']) && is_numeric($filters['committee_id'])) {
            $clauses[] = 'committee_id = '.(int) $filters['committee_id'];
        }

        if (! empty($filters['date_from']) && is_string($filters['date_from'])) {
            $clauses[] = 'sitting_date >= '.$this->quoted($filters['date_from']);
        }

        if (! empty($filters['date_to']) && is_string($filters['date_to'])) {
            $clauses[] = 'sitting_date <= '.$this->quoted($filters['date_to']);
        }

        return $clauses === [] ? null : implode(' AND ', $clauses);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function artifactFilterExpression(array $filters): ?string
    {
        $clauses = [];

        foreach (['media_family', 'source_module', 'mime_type', 'extension', 'subject_type'] as $field) {
            if (! empty($filters[$field]) && is_string($filters[$field])) {
                $clauses[] = $field.' = '.$this->quoted($filters[$field]);
            }
        }

        if (! empty($filters['subject_id']) && is_numeric($filters['subject_id'])) {
            $clauses[] = 'subject_id = '.(int) $filters['subject_id'];
        }

        if (! empty($filters['tag']) && is_string($filters['tag'])) {
            $clauses[] = 'tags = '.$this->quoted($filters['tag']);
        }

        return $clauses === [] ? null : implode(' AND ', $clauses);
    }

    private function quoted(string $value): string
    {
        return '"'.str_replace('"', '\"', $value).'"';
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $key = config('services.meilisearch.key') ?: env('MEILISEARCH_KEY');

        return array_filter([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $key ? 'Bearer '.$key : null,
        ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) (config('services.meilisearch.host') ?: env('MEILISEARCH_HOST', 'http://meilisearch:7700')), '/');
    }
}
