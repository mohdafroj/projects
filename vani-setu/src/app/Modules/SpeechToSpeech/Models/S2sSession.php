<?php

namespace App\Modules\SpeechToSpeech\Models;

use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class S2sSession extends Model
{
    protected $table = 's2s_sessions';

    protected $fillable = [
        'sitting_id',
        'started_by_user_id',
        'title',
        'mode',
        'input_source',
        'listener_scope',
        'source_lang',
        'target_lang',
        'available_target_langs',
        'audio_input_meta',
        'archive_meta',
        'fallback_meta',
        'announcement_text',
        'status',
        'engine_meta',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'engine_meta' => 'array',
        'available_target_langs' => 'array',
        'audio_input_meta' => 'array',
        'archive_meta' => 'array',
        'fallback_meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function sitting(): BelongsTo
    {
        return $this->belongsTo(Sitting::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(S2sSegment::class, 'session_id')->orderBy('sequence_no');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(S2sOutput::class, 'session_id')->latest('id');
    }
}
