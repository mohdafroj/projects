<?php

namespace App\Modules\Synopsis\Models;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SynopsisDocument extends Model
{
    protected $fillable = [
        'consolidation_id',
        'sitting_id',
        'writer_user_id',
        'chunk_code',
        'starts_at_offset_ms',
        'duration_ms',
        'source_mode',
        'status',
        'title',
        'body',
        'attributions',
        'ai_first_draft',
        'version',
        'submitted_at',
        'finalised_at',
        'finalised_by_user_id',
        'last_audit_log_id',
    ];

    protected $casts = [
        'attributions' => 'array',
        'ai_first_draft' => 'boolean',
        'submitted_at' => 'datetime',
        'finalised_at' => 'datetime',
    ];

    public function consolidation(): BelongsTo
    {
        return $this->belongsTo(ChiefConsolidation::class);
    }

    public function sitting(): BelongsTo
    {
        return $this->belongsTo(Sitting::class);
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'writer_user_id');
    }

    public function finalisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalised_by_user_id');
    }

    public function lastAuditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'last_audit_log_id');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(SynopsisDocumentEdit::class)->latest();
    }
}
