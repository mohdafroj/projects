<?php

namespace App\Modules\SpeechToSpeech\Services\Recheck;

use App\Modules\SpeechToSpeech\Models\S2sSegment;
use RuntimeException;

class NullSecondPassTranscriber implements SecondPassTranscriber
{
    public function retranscribe(S2sSegment $segment, SecondPassOptions $options): SecondPassResult
    {
        throw new RuntimeException(
            'second_pass_transcriber_not_configured: bind a concrete SecondPassTranscriber '
            .'(e.g. MlGatewayAsrTranscriber) in SpeechToSpeechServiceProvider to enable recheck.'
        );
    }
}
