<?php

namespace App\Modules\SpeechToSpeech\Services;

use App\Modules\Search\Services\ArtifactCatalogService;
use App\Modules\Search\Models\StoredArtifact;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Models\S2sSession;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S2sAudioArchive
{
    public function __construct(
        private readonly ArtifactCatalogService $artifacts,
        private readonly AudioChunkValidator $chunkValidator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function storeSourceUpload(UploadedFile $file, S2sSession $session, Request $request, ?int $sequence = null): array
    {
        // Ephemeral s2s: source audio is NEVER written to object storage. The
        // uploaded audio is forwarded in-memory to ml-gateway and discarded.
        // Returns an empty meta so callers record no path.
        return ['path' => null, 'disk' => null, 'stored' => false, 'archive_disabled' => true];
        // @phpstan-ignore-next-line — retained until the archive is fully removed.
        $chunk = $this->chunkValidator->validate($file);
        $disk = (string) config('filesystems.reporter_audio_disk', 's2s_input_audio');
        $originalName = $file->getClientOriginalName() ?: ($sequence === null ? 'session-audio.wav' : 'segment-'.$sequence.'.wav');
        $extension = $this->extensionFor($file, $originalName);
        $raw = (string) file_get_contents($file->getRealPath());
        $stored = $this->compressedPayload($raw, (string) $file->getMimeType(), $extension);
        $layout = $this->layoutMeta($session, $request, $sequence);
        $path = $this->archivePath($layout, $session, $sequence, $originalName, $stored['extension']);

        $artifact = $this->artifacts->storeAndRegister($stored['contents'], [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'title' => $originalName,
            'source_module' => 'speech_to_speech',
            'uploaded_by_user_id' => $request->user()?->id,
            'size_bytes' => strlen($stored['contents']),
            'tags' => array_filter(['s2s', 'audio-upload', 'source-audio', $stored['compressed'] ? 'compressed' : null]),
            'metadata' => [
                'original_name' => $originalName,
                'original_mime_type' => $file->getMimeType(),
                'stored_mime_type' => $stored['mime_type'],
                'original_size_bytes' => $file->getSize(),
                'stored_size_bytes' => strlen($stored['contents']),
                'compression' => $stored['compression'],
                'compression_ratio' => $stored['compression_ratio'],
                'archive_layout' => $layout,
                'audio_chunk' => $chunk->toArray(),
            ],
        ]);

        return [
            'disk' => $disk,
            'path' => $artifact->storage_path,
            'mime_type' => $file->getMimeType(),
            'stored_mime_type' => $stored['mime_type'],
            'original_name' => $originalName,
            'size' => $file->getSize(),
            'stored_size' => strlen($stored['contents']),
            'artifact_id' => $artifact->id,
            'compression' => $stored['compression'],
            'compressed' => $stored['compressed'],
            'compression_ratio' => $stored['compression_ratio'],
            'chunk' => $chunk->toArray(),
            'archive_layout' => $layout,
        ];
    }

    public function audioBytes(S2sSegment $segment): ?string
    {
        $meta = is_array($segment->engine_meta) ? $segment->engine_meta : [];
        $input = is_array($meta['input_audio'] ?? null) ? $meta['input_audio'] : [];
        $disk = (string) ($input['disk'] ?? config('filesystems.reporter_audio_disk', 's2s_input_audio'));
        $path = (string) ($input['path'] ?? $segment->source_audio_path ?? '');

        if ($path === '') {
            return null;
        }

        try {
            $bytes = Storage::disk($disk)->get($path);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        if (($input['compression'] ?? null) === 'gzip') {
            try {
                $decoded = gzdecode($bytes);
            } catch (\Throwable) {
                return null;
            }

            return is_string($decoded) && $decoded !== '' ? $decoded : null;
        }

        return $bytes;
    }

    /**
     * Remove source audio for finished sessions older than the retention
     * window while keeping transcript rows and audit metadata intact.
     *
     * @return array{eligible:int, pruned:int, bytes_released:int, missing:int, retained:int, dry_run:bool, cutoff:string}
     */
    public function pruneFinishedSourceAudio(int $retentionDays, int $limit = 500, bool $dryRun = false): array
    {
        $cutoff = now()->subDays(max(1, $retentionDays));
        $limit = max(1, $limit);
        $segments = S2sSegment::query()
            ->whereNotNull('source_audio_path')
            ->whereHas('session', fn ($query) => $query
                ->whereNotNull('finished_at')
                ->where('finished_at', '<', $cutoff))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $stats = [
            'eligible' => $segments->count(),
            'pruned' => 0,
            'bytes_released' => 0,
            'missing' => 0,
            'retained' => 0,
            'dry_run' => $dryRun,
            'cutoff' => $cutoff->toISOString(),
        ];

        foreach ($segments as $segment) {
            $meta = is_array($segment->engine_meta) ? $segment->engine_meta : [];
            $input = is_array($meta['input_audio'] ?? null) ? $meta['input_audio'] : [];
            $disk = (string) ($input['disk'] ?? config('filesystems.reporter_audio_disk', 's2s_input_audio'));
            $path = (string) ($segment->source_audio_path ?: ($input['path'] ?? ''));
            if ($path === '') {
                $stats['retained']++;
                continue;
            }

            $exists = false;
            $size = (int) ($input['stored_size'] ?? 0);
            try {
                $exists = Storage::disk($disk)->exists($path);
                if ($exists && $size <= 0) {
                    $size = (int) Storage::disk($disk)->size($path);
                }
            } catch (\Throwable) {
                $stats['retained']++;
                continue;
            }

            if (! $exists) {
                $stats['missing']++;
                if (! $dryRun) {
                    $this->markSegmentAudioPruned($segment, $disk, $path, 0, alreadyMissing: true);
                }
                continue;
            }

            if ($dryRun) {
                $stats['bytes_released'] += $size;
                $stats['pruned']++;
                continue;
            }

            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable) {
                $stats['retained']++;
                continue;
            }

            $this->markSegmentAudioPruned($segment, $disk, $path, $size);
            $stats['bytes_released'] += $size;
            $stats['pruned']++;
        }

        return $stats;
    }

    /**
     * @return array{contents:string, extension:string, mime_type:string, compression:?string, compressed:bool, compression_ratio:?float}
     */
    private function compressedPayload(string $raw, string $mimeType, string $extension): array
    {
        $shouldCompress = (bool) config('services.s2s.compress_source_audio', env('S2S_COMPRESS_SOURCE_AUDIO', true));
        $minimumBytes = max(1, (int) config('services.s2s.compress_min_bytes', env('S2S_COMPRESS_MIN_BYTES', 65536)));
        $compressible = in_array(strtolower($extension), ['wav', 'pcm'], true)
            || in_array(strtolower($mimeType), ['audio/wav', 'audio/x-wav', 'audio/wave'], true);

        if ($shouldCompress && $compressible && strlen($raw) >= $minimumBytes) {
            $gz = gzencode($raw, 6);
            if (is_string($gz) && strlen($gz) < strlen($raw)) {
                return [
                    'contents' => $gz,
                    'extension' => $extension.'.gz',
                    'mime_type' => 'application/gzip',
                    'compression' => 'gzip',
                    'compressed' => true,
                    'compression_ratio' => round(strlen($gz) / max(1, strlen($raw)), 4),
                ];
            }
        }

        return [
            'contents' => $raw,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'compression' => null,
            'compressed' => false,
            'compression_ratio' => null,
        ];
    }

    private function markSegmentAudioPruned(S2sSegment $segment, string $disk, string $path, int $storedSize, bool $alreadyMissing = false): void
    {
        $meta = is_array($segment->engine_meta) ? $segment->engine_meta : [];
        $input = is_array($meta['input_audio'] ?? null) ? $meta['input_audio'] : [];
        $artifactId = $input['artifact_id'] ?? null;
        $archiveLayout = is_array($input['archive_layout'] ?? null)
            ? $input['archive_layout']
            : $this->archiveLayoutFromPath($path, $segment->id, $input['archive_layout']['sequence_no'] ?? null);
        $meta['input_audio'] = array_merge($input, [
            'path' => null,
            'archive_layout' => $archiveLayout,
            'pruned_at' => now()->toISOString(),
            'pruned_reason' => 'retention_policy',
            'pruned_original_disk' => $disk,
            'pruned_original_path' => $path,
            'pruned_stored_size' => $storedSize,
            'pruned_already_missing' => $alreadyMissing,
        ]);

        $segment->forceFill([
            'source_audio_path' => null,
            'engine_meta' => $meta,
        ])->save();

        if (is_numeric($artifactId)) {
            $artifact = StoredArtifact::query()->find((int) $artifactId);
        } else {
            $artifact = StoredArtifact::query()
                ->where('stored_disk', $disk)
                ->where('storage_path', $path)
                ->first();
        }

        if (! $artifact instanceof StoredArtifact) {
            return;
        }

        $metadata = is_array($artifact->metadata) ? $artifact->metadata : [];
        $tags = is_array($artifact->tags) ? $artifact->tags : [];
        $artifact->forceFill([
            'storage_path' => null,
            'storage_uri' => null,
            'size_bytes' => 0,
            'tags' => array_values(array_unique([...$tags, 'pruned'])),
            'metadata' => array_merge($metadata, [
                'retention_pruned_at' => now()->toISOString(),
                'retention_original_disk' => $disk,
                'retention_original_path' => $path,
                'retention_stored_size_bytes' => $storedSize,
                'retention_already_missing' => $alreadyMissing,
            ]),
            'search_status' => 'blocked',
            'search_eligible' => false,
            'ai_eligible' => false,
            'last_hygiene_at' => now(),
        ])->save();
    }

    private function archivePath(array $layout, S2sSession $session, ?int $sequence, string $originalName, string $extension): string
    {
        $kind = $sequence === null ? 'session' : 'segments/'.$sequence;
        $base = pathinfo($originalName, PATHINFO_FILENAME) ?: ($sequence === null ? 'session-audio' : 'source');
        $safeBase = Str::slug(Str::limit($base, 48, ''), '-') ?: 'source-audio';

        return implode('/', [
            's2s',
            'devices',
            $layout['input_source'],
            $layout['device_bucket'],
            $layout['day'],
            $layout['hour'],
            'sessions',
            (string) $session->id,
            $kind,
            $safeBase.'.'.$extension,
        ]);
    }

    /**
     * @return array<string, string|int|null>
     */
    private function layoutMeta(S2sSession $session, Request $request, ?int $sequence): array
    {
        $deviceId = trim((string) ($request->input('capture_device_id') ?: $request->input('device_id') ?: $request->input('input_device_id') ?: 'default'));
        $inputSource = Str::slug((string) ($session->input_source ?: $request->input('input_source', 'microphone')), '-') ?: 'microphone';
        $started = $session->started_at ?: now();
        $day = $started->format('Y-m-d');
        $hour = $started->format('H');
        $deviceBucket = $deviceId === 'default' ? 'default' : 'device-'.substr(sha1($deviceId), 0, 12);
        $hierarchy = ['s2s', 'devices', $inputSource, $deviceBucket, $day, $hour, 'sessions', (string) $session->id];

        return [
            'input_source' => $inputSource,
            'device_bucket' => $deviceBucket,
            'day' => $day,
            'hour' => $hour,
            'session_id' => $session->id,
            'sequence_no' => $sequence,
            'hierarchy' => $hierarchy,
            'tags' => ['device', 'daywise', 'hourwise'],
            'partition_key' => implode('/', [$deviceBucket, $day, $hour]),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function archiveLayoutFromPath(string $path, int $segmentId, mixed $sequenceNo = null): ?array
    {
        if ($path === '') {
            return null;
        }

        $matches = null;
        foreach ([
            '#^s2s/devices/([^/]+)/([^/]+)/(\d{4}-\d{2}-\d{2})/(\d{2})/sessions/(\d+)(?:/|$)#',
            '#^s2s/devices/([^/]+)/(\d{4}-\d{2}-\d{2})/(\d{2})/sessions/(\d+)(?:/|$)#',
        ] as $pattern) {
            if (preg_match($pattern, $path, $candidateMatches)) {
                $matches = $candidateMatches;
                break;
            }
        }

        if (! is_array($matches)) {
            return null;
        }

        if (count($matches) === 6) {
            $inputSource = $matches[1];
            $deviceBucket = $matches[2];
            $day = $matches[3];
            $hour = $matches[4];
            $sessionId = $matches[5];
        } else {
            $inputSource = $matches[1];
            $deviceBucket = 'default';
            $day = $matches[2];
            $hour = $matches[3];
            $sessionId = $matches[4];
        }

        return [
            'input_source' => $inputSource,
            'device_bucket' => $deviceBucket,
            'day' => $day,
            'hour' => $hour,
            'session_id' => is_numeric($sessionId) ? (int) $sessionId : $sessionId,
            'sequence_no' => is_numeric($sequenceNo) ? (int) $sequenceNo : $sequenceNo,
            'hierarchy' => ['s2s', 'devices', $inputSource, $deviceBucket, $day, $hour, 'sessions', $sessionId],
            'tags' => ['device', 'daywise', 'hourwise'],
            'partition_key' => implode('/', [$deviceBucket, $day, $hour]),
            'segment_id' => $segmentId,
        ];
    }

    private function extensionFor(UploadedFile $file, string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== '') {
            return preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        }

        return match (strtolower((string) $file->getMimeType())) {
            'audio/wav', 'audio/x-wav', 'audio/wave' => 'wav',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/webm', 'video/webm' => 'webm',
            default => 'bin',
        };
    }
}
