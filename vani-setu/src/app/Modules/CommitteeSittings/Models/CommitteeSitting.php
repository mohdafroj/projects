<?php

namespace App\Modules\CommitteeSittings\Models;

use App\Modules\Core\Models\Sitting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommitteeSitting extends Model
{
    protected $fillable = [
        'committee_id',
        'sitting_id',
        'meeting_no',
        'scheduled_at',
        'venue',
        'status',
        'in_camera_default',
        'witnesses',
        'observers',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'in_camera_default' => 'boolean',
        'witnesses' => 'array',
        'observers' => 'array',
    ];

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function sitting(): BelongsTo
    {
        return $this->belongsTo(Sitting::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CommitteeDocument::class);
    }
}
