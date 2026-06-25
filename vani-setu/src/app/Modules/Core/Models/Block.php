<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    protected $fillable = [
        'slot_id',
        'committee_id',
        'source_type',
        'in_camera_flag',
        'sequence',
        'start_ms',
        'end_ms',
        'original_lang',
        'chief_lang',
        'ai_action',
        'ai_text',
        'text',
        'translated_text',
        'member_id',
        'custom_member_id',
        'version',
        'reporter_edit_count',
    ];

    protected $casts = [
        'in_camera_flag' => 'boolean',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function customMember(): BelongsTo
    {
        return $this->belongsTo(MemberCustom::class, 'custom_member_id');
    }

    public function scopeEditableInLane(Builder $query, string $langRole): Builder
    {
        return $query->where('original_lang', $langRole);
    }
}
