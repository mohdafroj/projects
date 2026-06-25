<?php

namespace App\Modules\Capture\Services;

use App\Modules\Core\Models\Slot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ReporterAudioStorage
{
    public function diskName(): string
    {
        return (string) config('filesystems.reporter_audio_disk', 'vani_audio');
    }

    public function chunkPath(Slot $slot, int $sequence, ?UploadedFile $chunk = null): string
    {
        $extension = $this->extensionFor($chunk);

        return "reporter-audio/{$slot->id}/chunk-{$sequence}.{$extension}";
    }

    public function finalPath(Slot $slot, string $extension = 'webm'): string
    {
        return "reporter-audio/{$slot->id}/slot-{$slot->id}.{$extension}";
    }

    public function uri(string $path): string
    {
        $bucket = (string) config('filesystems.disks.'.$this->diskName().'.bucket', 'vani-audio-raw-rs');

        return "s3://{$bucket}/{$path}";
    }

    public function putChunk(Slot $slot, int $sequence, UploadedFile $chunk): array
    {
        $path = $this->chunkPath($slot, $sequence, $chunk);
        $bytes = file_get_contents($chunk->getRealPath());
        Storage::disk($this->diskName())->put($path, $bytes);

        return [
            'path' => $path,
            'uri' => $this->uri($path),
            'bytes' => strlen($bytes),
            'mime_type' => $chunk->getMimeType(),
            'original_name' => $chunk->getClientOriginalName(),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public function chunks(Slot $slot): Collection
    {
        $dir = "reporter-audio/{$slot->id}";

        return collect(Storage::disk($this->diskName())->files($dir))
            ->filter(fn (string $path) => preg_match('/chunk-(\d+)\.[a-z0-9]+$/i', $path) === 1)
            ->sortBy(fn (string $path) => (int) preg_replace('/^.*chunk-(\d+)\.[a-z0-9]+$/i', '$1', $path))
            ->values();
    }

    /**
     * @return array{path:string, uri:string, bytes:int, sha256:string}
     */
    public function assembleFinal(Slot $slot, Collection $chunks): array
    {
        $disk = Storage::disk($this->diskName());
        $payload = '';
        foreach ($chunks as $chunk) {
            $payload .= (string) $disk->get($chunk);
        }

        $extension = strtolower(pathinfo((string) $chunks->first(), PATHINFO_EXTENSION)) ?: 'webm';
        $path = $this->finalPath($slot, $extension);
        $disk->put($path, $payload);

        return [
            'path' => $path,
            'uri' => $this->uri($path),
            'bytes' => strlen($payload),
            'sha256' => hash('sha256', $payload),
        ];
    }

    private function extensionFor(?UploadedFile $chunk): string
    {
        $extension = strtolower((string) ($chunk?->getClientOriginalExtension() ?: $chunk?->extension() ?: ''));

        return in_array($extension, ['webm', 'wav', 'mp3', 'm4a', 'mp4', 'ogg'], true) ? $extension : 'webm';
    }
}
