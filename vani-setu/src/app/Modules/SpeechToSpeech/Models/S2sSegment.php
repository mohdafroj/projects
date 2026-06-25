<?php

namespace App\Modules\SpeechToSpeech\Models;

use App\Modules\Core\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class S2sSegment extends Model
{
    /**
     * Segment QA states that downstream consumers (synopsis export,
     * archive, playback assembly) are allowed to read transcripts
     * from. Anything else is either still pending recheck, has drifted
     * past the threshold, or failed/skipped — those segments must not
     * feed "further processing" until a human reviews them.
     */
    public const QA_APPROVED_STATES = ['passed', 'corrected'];


    protected $table = 's2s_segments';

    protected $fillable = [
        'session_id',
        'sequence_no',
        'start_ms',
        'end_ms',
        'source_language',
        'source_text',
        'target_text',
        'source_audio_path',
        'target_audio_path',
        'status',
        'translated_segments',
        'engine_meta',
        'audit_log_id',
        'qa_state',
        'qa_score',
        'qa_corrected_text',
        'qa_engine_meta',
        'qa_checked_at',
        'qa_attempts',
    ];

    protected $casts = [
        'translated_segments' => 'array',
        'engine_meta' => 'array',
        'qa_engine_meta' => 'array',
        'qa_score' => 'float',
        'qa_checked_at' => 'datetime',
        'qa_attempts' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(S2sSession::class, 'session_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(S2sOutput::class, 'segment_id')->orderBy('language_code');
    }

    /**
     * Filter to segments whose recheck verdict is good enough to feed
     * downstream consumers. Use this scope wherever segment transcripts
     * are read for export / synopsis / archive — the user's directive
     * is that those paths run "before further processing" only on
     * approved verdicts.
     */
    public function scopeQaApproved(Builder $query): Builder
    {
        return $query->whereIn('qa_state', self::QA_APPROVED_STATES);
    }

    /**
     * The transcript text downstream consumers should read: the
     * recheck-engine's corrected text if available, else the live
     * source_text. Falls back to source_text when qa_state is anything
     * other than 'corrected' so callers can use this uniformly.
     */
    public function getApprovedTranscriptAttribute(): ?string
    {
        if ($this->qa_state === 'corrected' && filled($this->qa_corrected_text)) {
            return (string) $this->qa_corrected_text;
        }
        return $this->source_text;
    }

    public function hasActiveSourceAudio(): bool
    {
        return filled($this->source_audio_path);
    }

    public function hasPrunedSourceAudioRecord(): bool
    {
        $input = (array) data_get($this->engine_meta, 'input_audio', []);

        return filled($input['pruned_at'] ?? null)
            || filled($input['pruned_original_path'] ?? null)
            || ((int) ($input['pruned_stored_size'] ?? 0) > 0);
    }

    public function hasSourceAudioLinkage(): bool
    {
        return $this->hasActiveSourceAudio() || $this->hasPrunedSourceAudioRecord();
    }
}
