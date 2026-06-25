<?php

namespace App\Modules\SpeechToSpeech\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 4 — per-listener language channels (UN-booth).
 *
 * Broadcast once per (segment, target language) AFTER the segment's outputs are
 * persisted — i.e. off the speaker's hot streaming path, so listener fan-out
 * never adds latency to the sub-1s speak→hear loop. Each listener subscribes to
 * the public channel for the session + language they chose and renders the live
 * translated text (and plays the audio when a media URL is present — audio
 * auto-flows once MinIO is wired into the gateway; until then text-only).
 *
 * Public (un-authenticated) channel: a listener only needs the session id +
 * language, like picking a booth channel; no per-listener auth.
 */
class S2sLiveOutput implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  {seq, text, audio_url, audio_mime, status}
     */
    public function __construct(
        public int $sessionId,
        public string $languageCode,
        public array $payload,
    ) {}

    public function broadcastOn(): Channel
    {
        // Channel names can't contain ':' — sanitise the BCP-47 tag (hi-IN ok,
        // but be defensive for any colon/dot variants).
        $lang = str_replace([':', '.', '/'], '-', $this->languageCode);

        return new Channel("s2s-live.{$this->sessionId}.{$lang}");
    }

    public function broadcastAs(): string
    {
        return 'output';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload + ['language_code' => $this->languageCode];
    }
}
