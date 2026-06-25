<?php

namespace App\Modules\Search\Services;

use App\Modules\Search\Models\StoredArtifact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArtifactCatalogService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function storeAndRegister(string $contents, array $attributes): StoredArtifact
    {
        $disk = $this->nullableString($attributes['disk'] ?? null) ?: (string) config('services.artifact_catalog.default_disk', 'vani_artifacts');
        $path = $this->nullableString($attributes['path'] ?? null)
            ?: trim((string) ($attributes['prefix'] ?? 'artifacts'), '/').'/'.Str::uuid().'.'.($this->nullableString($attributes['extension'] ?? null) ?: 'bin');

        Storage::disk($disk)->put($path, $contents);

        $attributes['disk'] = $disk;
        $attributes['path'] = $path;
        $attributes['size_bytes'] = strlen($contents);
        $attributes['sha256'] = hash('sha256', $contents);

        return $this->registerStoredObject($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function storeUploadedFileAndRegister(UploadedFile $file, string $prefix, array $attributes): StoredArtifact
    {
        $disk = $this->nullableString($attributes['disk'] ?? null) ?: (string) config('services.artifact_catalog.default_disk', 'vani_artifacts');
        $path = Storage::disk($disk)->putFile($prefix, $file);

        $attributes['disk'] = $disk;
        $attributes['path'] = $path;
        $attributes['mime_type'] = $attributes['mime_type'] ?? $file->getMimeType();
        $attributes['title'] = $attributes['title'] ?? ($file->getClientOriginalName() ?: basename($path));
        $attributes['size_bytes'] = $attributes['size_bytes'] ?? $file->getSize();

        return $this->registerStoredObject($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function registerStoredObject(array $attributes): StoredArtifact
    {
        $this->assertRequiredAttributes($attributes);

        $disk = $this->nullableString($attributes['disk'] ?? null);
        $path = $this->nullableString($attributes['path'] ?? null);
        $mimeType = $this->nullableString($attributes['mime_type'] ?? null);
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION)) ?: null;
        $mediaFamily = $this->detectMediaFamily($mimeType, $extension);
        $sensitivity = $this->nullableString($attributes['sensitivity_classification'] ?? null) ?: 'non_sensitive';
        $metadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [];
        $searchText = $this->normalizedText($attributes['search_text'] ?? null);

        $artifact = StoredArtifact::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => $this->artifactTitle($attributes, $path, $mediaFamily),
            'stored_disk' => $disk,
            'storage_path' => $path,
            'storage_uri' => $this->nullableString($attributes['storage_uri'] ?? null) ?: $this->storageUri($disk, $path),
            'mime_type' => $mimeType,
            'extension' => $extension,
            'media_family' => $mediaFamily,
            'sensitivity_classification' => $sensitivity,
            'source_system' => $this->nullableString($attributes['source_system'] ?? null) ?: 'vani_setu',
            'source_module' => $this->nullableString($attributes['source_module'] ?? null),
            'subject_type' => $this->nullableString($attributes['subject_type'] ?? null),
            'subject_id' => isset($attributes['subject_id']) && is_numeric($attributes['subject_id']) ? (int) $attributes['subject_id'] : null,
            'uploaded_by_user_id' => isset($attributes['uploaded_by_user_id']) && is_numeric($attributes['uploaded_by_user_id']) ? (int) $attributes['uploaded_by_user_id'] : null,
            'size_bytes' => $this->integerOrNull($attributes['size_bytes'] ?? null) ?? $this->safeSize($disk, $path),
            'sha256' => $this->nullableString($attributes['sha256'] ?? null) ?: $this->safeSha256($disk, $path),
            'tags' => $this->normalizedTags(array_merge((array) ($attributes['tags'] ?? []), [$mediaFamily, $extension])),
            'metadata' => $metadata,
            'metadata_text' => $this->metadataText($metadata),
            'search_text' => $searchText,
            'ai_eligible' => $sensitivity === 'non_sensitive',
            'search_eligible' => $sensitivity === 'non_sensitive',
            'classification_status' => 'ready',
            'search_status' => $sensitivity === 'non_sensitive' ? 'pending' : 'blocked',
            'last_hygiene_at' => now(),
        ]);

        return $artifact;
    }

    public function hydrateForHygiene(StoredArtifact $artifact): StoredArtifact
    {
        $updates = [
            'classification_status' => 'ready',
            'last_hygiene_at' => now(),
        ];

        if (! $artifact->isNonSensitive()) {
            $updates['ai_eligible'] = false;
            $updates['search_eligible'] = false;
            $updates['search_status'] = 'blocked';
            $artifact->forceFill($updates)->save();

            return $artifact->refresh();
        }

        if ($artifact->size_bytes === null) {
            $updates['size_bytes'] = $this->safeSize($artifact->stored_disk, $artifact->storage_path);
        }

        if (! $artifact->sha256) {
            $updates['sha256'] = $this->safeSha256($artifact->stored_disk, $artifact->storage_path);
        }

        $searchText = $artifact->search_text ?: $this->extractTextPreview($artifact);
        if ($searchText !== null && $searchText !== '') {
            $updates['search_text'] = $searchText;
        }

        $updates['metadata_text'] = $this->metadataText(is_array($artifact->metadata) ? $artifact->metadata : []);
        $updates['search_status'] = 'pending';

        $artifact->forceFill($updates)->save();

        return $artifact->refresh();
    }

    private function artifactTitle(array $attributes, ?string $path, string $mediaFamily): string
    {
        $title = $this->nullableString($attributes['title'] ?? null);
        if ($title !== null) {
            return $title;
        }

        if ($path !== null) {
            return basename($path);
        }

        return Str::headline($mediaFamily).' Artifact';
    }

    private function storageUri(?string $disk, ?string $path): ?string
    {
        if ($disk === null || $path === null) {
            return null;
        }

        return sprintf('%s://%s', $disk, ltrim($path, '/'));
    }

    private function safeSize(?string $disk, ?string $path): ?int
    {
        if ($disk === null || $path === null) {
            return null;
        }

        try {
            return (int) Storage::disk($disk)->size($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeSha256(?string $disk, ?string $path): ?string
    {
        if ($disk === null || $path === null) {
            return null;
        }

        try {
            $contents = Storage::disk($disk)->get($path);

            return hash('sha256', $contents);
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractTextPreview(StoredArtifact $artifact): ?string
    {
        if (! in_array($artifact->media_family, ['text', 'json'], true) || ! $artifact->stored_disk || ! $artifact->storage_path) {
            return null;
        }

        try {
            $contents = Storage::disk($artifact->stored_disk)->get($artifact->storage_path);
        } catch (\Throwable) {
            return null;
        }

        return $this->normalizedText($contents);
    }

    private function detectMediaFamily(?string $mimeType, ?string $extension): string
    {
        $mime = strtolower((string) $mimeType);
        $ext = strtolower((string) $extension);

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'audio/') => 'audio',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'text/') => 'text',
            str_contains($mime, 'json') => 'json',
            str_contains($mime, 'pdf') => 'pdf',
            in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true) => 'document',
            in_array($ext, ['xls', 'xlsx', 'csv', 'tsv', 'ods'], true) => 'spreadsheet',
            in_array($ext, ['zip', 'tar', 'gz', '7z'], true) => 'archive',
            default => 'binary',
        };
    }

    /**
     * @param  array<int, mixed>  $tags
     * @return array<int, string>
     */
    private function normalizedTags(array $tags): array
    {
        $values = collect($tags)
            ->filter(fn ($tag) => is_scalar($tag) && trim((string) $tag) !== '')
            ->map(fn ($tag) => Str::lower(trim((string) $tag)))
            ->unique()
            ->values()
            ->all();

        return array_values($values);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function metadataText(array $metadata): ?string
    {
        $values = [];
        array_walk_recursive($metadata, function ($value) use (&$values) {
            if (is_scalar($value)) {
                $values[] = (string) $value;
            }
        });

        $text = trim(implode(' ', $values));

        return $text !== '' ? Str::limit($text, 20000, '') : null;
    }

    private function normalizedText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? Str::limit($text, 50000, '') : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertRequiredAttributes(array $attributes): void
    {
        foreach (['mime_type', 'title', 'source_module'] as $field) {
            if ($this->nullableString($attributes[$field] ?? null) === null) {
                throw new \InvalidArgumentException("Artifact attribute [{$field}] is required.");
            }
        }
    }
}
