<?php

namespace App\Modules\Regional\Models;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegionalCase extends Model
{
    protected $table = 'regional_cases';

    protected $fillable = [
        'sitting_id',
        'slot_id',
        'block_id',
        'requester_user_id',
        'specialist_user_id',
        'source_language',
        'target_language',
        'detector',
        'detection_confidence',
        'domain',
        'status',
        'source_text',
        'machine_translation',
        'specialist_translation',
        'routing_meta',
        'translation_meta',
    ];

    protected $casts = [
        'detection_confidence' => 'float',
        'routing_meta' => 'array',
        'translation_meta' => 'array',
    ];

    public function sitting(): BelongsTo
    {
        return $this->belongsTo(Sitting::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specialist_user_id');
    }

    public function crossChecks(): HasMany
    {
        return $this->hasMany(RegionalCrossCheck::class, 'case_id');
    }

    public function isSealed(): bool
    {
        return in_array($this->status, ['committed'], true);
    }
}
