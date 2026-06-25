<?php

namespace App\Modules\SpeechToSpeech\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SpeechToSpeech\Models\S2sSegment;
use App\Modules\SpeechToSpeech\Services\S2sAudioArchive;
use App\Modules\SpeechToSpeech\Services\Recheck\InternalAudioUrlSigner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Internal-only endpoint that streams a single S2S segment's source
 * audio bytes to consumers reachable on the docker-internal network
 * (currently: the ml-gateway running the second-pass ASR for the
 * recheck engine). All requests are HMAC-signed with a short TTL;
 * unsigned requests are rejected.
 */
class InternalAudioController extends Controller
{
    public function show(int $segmentId, Request $request, InternalAudioUrlSigner $signer, S2sAudioArchive $audioArchive): Response
    {
        $expires = (int) $request->query('e', 0);
        $token = (string) $request->query('t', '');
        if ($expires === 0 || $token === '') {
            abort(403, 'missing signature');
        }
        if (! $signer->verify($segmentId, $expires, $token)) {
            abort(403, 'invalid or expired signature');
        }

        $segment = S2sSegment::query()->find($segmentId);
        if ($segment === null || ! filled($segment->source_audio_path)) {
            abort(404, 'segment audio not found');
        }

        $mime = $segment->engine_meta['input_audio']['mime_type']
            ?? ($segment->engine_meta['input_audio']['chunk']['mime'] ?? 'application/octet-stream');

        $audio = $audioArchive->audioBytes($segment);
        if ($audio === null) {
            return response()->json([
                'error' => 'audio_unreadable',
                'message' => 'Stored segment audio exists but could not be decoded for replay.',
            ], 422, ['Cache-Control' => 'no-store']);
        }

        return new StreamedResponse(
            function () use ($audio) {
                echo $audio;
            },
            200,
            [
                'Content-Type' => is_string($mime) ? $mime : 'application/octet-stream',
                'Cache-Control' => 'no-store',
                'X-Vani-S2s-Segment' => (string) $segmentId,
            ],
        );
    }
}
