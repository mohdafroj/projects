<?php

namespace App\Modules\SpeechToSpeech\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class S2sOutput extends Model
{
    protected $table = 's2s_outputs';

    protected $fillable = [
        'session_id',
        'segment_id',
        'language_code',
        'channel_name',
        'status',
        'text_output',
        'audio_output_path',
        'output_meta',
    ];

    protected $casts = [
        'output_meta' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(S2sSession::class, 'session_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(S2sSegment::class, 'segment_id');
    }
}
