<?php

namespace App\Modules\Formatting\Models;

use App\Modules\Core\Models\Block;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormattingLine extends Model
{
    protected $fillable = [
        'job_id',
        'block_id',
        'sequence',
        'kind',
        'lang',
        'speaker_label',
        'body',
        'page_number',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(FormattingJob::class, 'job_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}
