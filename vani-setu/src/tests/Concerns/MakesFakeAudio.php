<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Builds real-shaped WAV uploads for feature tests. After slice A
 * shipped AudioChunkValidator, S2sController::storeAudio rejects
 * any "audio/wav" upload whose first 44 bytes aren't a valid RIFF
 * header — that's intentional for the chunk-validation guarantee
 * but it broke tests that used UploadedFile::fake()->create('x.wav')
 * (which only sets the mime + size, not the bytes).
 *
 * Use ``$this->fakeWavUpload()`` instead. Produces a syntactically
 * valid 16-bit PCM mono WAV with silent audio of the requested
 * duration, sample-rate, and filename — passes the chunk validator
 * cleanly.
 */
trait MakesFakeAudio
{
    protected function fakeWavUpload(
        string $filename = 'segment.wav',
        int $durationMs = 1000,
        int $sampleRate = 16000,
    ): UploadedFile {
        $bits = 16;
        $channels = 1;
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
            .pack('v', 1)        // PCM
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

        return new UploadedFile($path, $filename, 'audio/wav', null, true);
    }
}
