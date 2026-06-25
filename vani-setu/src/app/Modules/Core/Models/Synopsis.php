<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Synopsis extends Model
{
    protected $fillable = [
        'sitting_id',
        'source_file',
        'language',
        'kind',
        'sequence',
        'speaker_name',
        'party',
        'constituency',
        'summary_text',
    ];

    public function sitting(): BelongsTo
    {
        return $this->belongsTo(Sitting::class);
    }
}
