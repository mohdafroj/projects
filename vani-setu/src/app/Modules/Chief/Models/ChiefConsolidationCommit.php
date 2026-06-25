<?php

namespace App\Modules\Chief\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiefConsolidationCommit extends Model
{
    protected $fillable = [
        'consolidation_id',
        'chief_user_id',
        'lang_side',
        'block_count',
        'edit_count',
        'custom_member_count',
        'committed_at',
        'committed_audit_log_id',
    ];

    protected $casts = ['committed_at' => 'datetime'];

    public function consolidation(): BelongsTo
    {
        return $this->belongsTo(ChiefConsolidation::class, 'consolidation_id');
    }

    public function chief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chief_user_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'committed_audit_log_id');
    }
}
