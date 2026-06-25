<?php

namespace App\Modules\SpeechToSpeech\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class S2sRuntimeConfig extends Model
{
    protected $table = 's2s_runtime_configs';

    protected $fillable = [
        'config_key',
        'config_value',
        'edited_by_user_id',
    ];

    protected $casts = [
        'config_value' => 'array',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }
}
