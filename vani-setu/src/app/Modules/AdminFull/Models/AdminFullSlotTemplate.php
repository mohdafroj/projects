<?php

namespace App\Modules\AdminFull\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminFullSlotTemplate extends Model
{
    protected $fillable = [
        'sitting_template_id',
        'name',
        'code_prefix',
        'start_offset_ms',
        'duration_ms',
        'topic',
        'lang_roles',
        'is_active',
    ];

    protected $casts = [
        'lang_roles' => 'array',
        'is_active' => 'boolean',
    ];

    public function sittingTemplate(): BelongsTo
    {
        return $this->belongsTo(AdminFullSittingTemplate::class, 'sitting_template_id');
    }
}
