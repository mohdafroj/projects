<?php

namespace App\Modules\SpeechToSpeech\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AudioChunkValidator
{
    private const ALLOWED_MIMES = [
        'audio/wav',
        'audio/x-wav',
        'audio/wave',
        'audio/webm',
        'audio/ogg',
        'audio/mpeg',
        'audio/mp3',
    ];

    private const MIN_BYTES = 4096;
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MIN_DURATION_MS = 400;
    private const MAX_DURATION_MS = 30_000;
    private const ALLOWED_SAMPLE_RATES = [8000, 16000, 22050, 24000, 32000, 44100, 48000];

    public function validate(UploadedFile $file, string $field = 'audio'): AudioChunkMeta
    {
        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                $field => "Unsupported audio mime type: {$mime}",
            ]);
        }

        $bytes = (int) $file->getSize();
        if ($bytes < self::MIN_BYTES) {
            throw ValidationException::withMessages([
                $field => "Audio chunk too small ({$bytes} bytes; minimum ".self::MIN_BYTES.").",
            ]);
        }
        if ($bytes > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                $field => "Audio chunk too large ({$bytes} bytes; maximum ".self::MAX_BYTES.").",
            ]);
        }

        $path = $file->getRealPath();
        $sha256 = hash_file('sha256', $path) ?: '';

        $wav = null;
        if (in_array($mime, ['audio/wav', 'audio/x-wav', 'audio/wave'], true)) {
            $wav = $this->parseWav($path);
            if ($wav === null) {
                throw ValidationException::withMessages([
                    $field => 'Audio file declared as WAV but header is invalid.',
                ]);
            }

            if ($wav['duration_ms'] < self::MIN_DURATION_MS) {
                throw ValidationException::withMessages([
                    $field => "Audio too short ({$wav['duration_ms']} ms; minimum ".self::MIN_DURATION_MS." ms).",
                ]);
            }
            if ($wav['duration_ms'] > self::MAX_DURATION_MS) {
                throw ValidationException::withMessages([
                    $field => "Audio too long ({$wav['duration_ms']} ms; maximum ".self::MAX_DURATION_MS." ms).",
                ]);
            }
            if (! in_array($wav['sample_rate'], self::ALLOWED_SAMPLE_RATES, true)) {
                throw ValidationException::withMessages([
                    $field => "Unsupported WAV sample rate: {$wav['sample_rate']} Hz.",
                ]);
            }
            if ($wav['channels'] > 2) {
                throw ValidationException::withMessages([
                    $field => "Unsupported channel count: {$wav['channels']} (mono/stereo only).",
                ]);
            }
        }

        return new AudioChunkMeta(
            mime: $mime,
            bytes: $bytes,
            sha256: $sha256,
            durationMs: $wav['duration_ms'] ?? null,
            sampleRate: $wav['sample_rate'] ?? null,
            channels: $wav['channels'] ?? null,
            bitsPerSample: $wav['bits_per_sample'] ?? null,
            codec: $wav !== null ? 'pcm_s'.$wav['bits_per_sample'].'le' : $mime,
        );
    }

    private function parseWav(string $path): ?array
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return null;
        }
        try {
            $header = fread($fh, 44);
            if ($header === false || strlen($header) < 44) {
                return null;
            }
            if (substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE') {
                return null;
            }

            $fmt = unpack(
                'vaudio_format/vchannels/Vsample_rate/Vbyte_rate/vblock_align/vbits',
                substr($header, 20, 16)
            );
            if ($fmt === false || ($fmt['audio_format'] !== 1 && $fmt['audio_format'] !== 3)) {
                return null;
            }

            $dataSize = $this->findDataChunkSize($fh, $header);
            if ($dataSize === null || $fmt['byte_rate'] <= 0) {
                return null;
            }

            $durationMs = (int) round(($dataSize / $fmt['byte_rate']) * 1000);

            return [
                'channels' => (int) $fmt['channels'],
                'sample_rate' => (int) $fmt['sample_rate'],
                'byte_rate' => (int) $fmt['byte_rate'],
                'bits_per_sample' => (int) $fmt['bits'],
                'data_size' => $dataSize,
                'duration_ms' => $durationMs,
            ];
        } finally {
            fclose($fh);
        }
    }

    private function findDataChunkSize($fh, string $header): ?int
    {
        if (substr($header, 36, 4) === 'data') {
            $sub = unpack('Vsize', substr($header, 40, 4));
            return $sub !== false ? (int) $sub['size'] : null;
        }

        fseek($fh, 12);
        while (! feof($fh)) {
            $chunk = fread($fh, 8);
            if ($chunk === false || strlen($chunk) < 8) {
                return null;
            }
            $id = substr($chunk, 0, 4);
            $sizePart = unpack('Vsize', substr($chunk, 4, 4));
            if ($sizePart === false) {
                return null;
            }
            $size = (int) $sizePart['size'];
            if ($id === 'data') {
                return $size;
            }
            if (fseek($fh, $size, SEEK_CUR) !== 0) {
                return null;
            }
        }
        return null;
    }
}
