<?php

namespace App\Modules\SpeechToSpeech\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class S2sVocabularyRule extends Model
{
    protected $table = 's2s_vocabulary_rules';

    public const RULE_TYPES = [
        'replacement',
        'correction',
        'phonetic',
        'blocked',
        'bad_word',
        'shadow_word',
        'filler',
    ];

    protected $fillable = [
        'rule_type',
        'language_code',
        'source_phrase',
        'replacement_text',
        'phonetic_hint',
        'priority',
        'is_active',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
