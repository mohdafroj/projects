<?php

namespace App\Modules\SpeechToSpeech\Services\Recheck;

use App\Modules\SpeechToSpeech\Models\S2sSegment;

interface SecondPassTranscriber
{
    /**
     * Re-transcribe the audio attached to the given segment with
     * QA-grade settings (temperature 0, glossary prompts, optional
     * neighbour-segment context). Returns a typed result.
     */
    public function retranscribe(S2sSegment $segment, SecondPassOptions $options): SecondPassResult;
}
