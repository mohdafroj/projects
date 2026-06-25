<?php

namespace Tests\Unit\SpeechToSpeech;

use App\Modules\SpeechToSpeech\Services\AudioChunkValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AudioChunkValidatorTest extends TestCase
{
    public function test_accepts_well_formed_wav_chunk(): void
    {
        $validator = new AudioChunkValidator();
        $file = $this->makeWavUpload(durationMs: 2000, sampleRate: 16000, channels: 1, bits: 16);

        $meta = $validator->validate($file);

        $this->assertSame(2000, $meta->durationMs);
        $this->assertSame(16000, $meta->sampleRate);
        $this->assertSame(1, $meta->channels);
        $this->assertSame(16, $meta->bitsPerSample);
        $this->assertSame('pcm_s16le', $meta->codec);
        $this->assertNotEmpty($meta->sha256);
        $this->assertGreaterThan(4096, $meta->bytes);
    }

    public function test_rejects_chunk_smaller_than_min_bytes(): void
    {
        $validator = new AudioChunkValidator();
        $path = tempnam(sys_get_temp_dir(), 'wav');
        file_put_contents($path, str_repeat("\0", 100));
        $file = new UploadedFile($path, 'tiny.wav', 'audio/wav', null, true);

        $this->expectException(ValidationException::class);
        $validator->validate($file);
    }

    public function test_rejects_unsupported_mime(): void
    {
        $validator = new AudioChunkValidator();
        $path = tempnam(sys_get_temp_dir(), 'bin');
        file_put_contents($path, str_repeat('x', 8192));
        $file = new UploadedFile($path, 'data.bin', 'application/octet-stream', null, true);

        $this->expectException(ValidationException::class);
        $validator->validate($file);
    }

    public function test_rejects_wav_with_invalid_header(): void
    {
        $validator = new AudioChunkValidator();
        $path = tempnam(sys_get_temp_dir(), 'wav');
        file_put_contents($path, str_repeat("\xFF", 8192));
        $file = new UploadedFile($path, 'fake.wav', 'audio/wav', null, true);

        $this->expectException(ValidationException::class);
        $validator->validate($file);
    }

    public function test_rejects_wav_too_short(): void
    {
        $validator = new AudioChunkValidator();
        // 200ms is below the 400ms minimum
        $file = $this->makeWavUpload(durationMs: 200, sampleRate: 16000, channels: 1, bits: 16);

        $this->expectException(ValidationException::class);
        $validator->validate($file);
    }

    public function test_rejects_wav_too_long(): void
    {
        $validator = new AudioChunkValidator();
        // 35000ms > 30000ms max
        $file = $this->makeWavUpload(durationMs: 35_000, sampleRate: 16000, channels: 1, bits: 16);

        $this->expectException(ValidationException::class);
        $validator->validate($file);
    }

    public function test_rejects_unsupported_sample_rate(): void
    {
        $validator = new AudioChunkValidator();
        $file = $this->makeWavUpload(durationMs: 2000, sampleRate: 11025, channels: 1, bits: 16);

        $this->expectException(ValidationException::class);
        $validator->validate($file);
    }

    public function test_rejects_too_many_channels(): void
    {
        $validator = new AudioChunkValidator();
        $file = $this->makeWavUpload(durationMs: 2000, sampleRate: 16000, channels: 6, bits: 16);

        $this->expectException(ValidationException::class);
        $validator->validate($file);
    }

    private function makeWavUpload(int $durationMs, int $sampleRate, int $channels, int $bits): UploadedFile
    {
        $bytesPerSample = (int) ($bits / 8);
        $byteRate = $sampleRate * $channels * $bytesPerSample;
        $blockAlign = $channels * $bytesPerSample;
        $dataSize = (int) round(($durationMs / 1000) * $byteRate);
        $chunkSize = 36 + $dataSize;

        $header = 'RIFF'
            .pack('V', $chunkSize)
            .'WAVE'
            .'fmt '
            .pack('V', 16)
            .pack('v', 1)
            .pack('v', $channels)
            .pack('V', $sampleRate)
            .pack('V', $byteRate)
            .pack('v', $blockAlign)
            .pack('v', $bits)
            .'data'
            .pack('V', $dataSize);

        $path = tempnam(sys_get_temp_dir(), 'wav');
        $fh = fopen($path, 'wb');
        fwrite($fh, $header);
        $chunk = str_repeat("\0", 4096);
        $written = 0;
        while ($written < $dataSize) {
            $remaining = $dataSize - $written;
            $write = $remaining < 4096 ? substr($chunk, 0, $remaining) : $chunk;
            fwrite($fh, $write);
            $written += strlen($write);
        }
        fclose($fh);

        return new UploadedFile($path, 'segment.wav', 'audio/wav', null, true);
    }
}
