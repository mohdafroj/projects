<?php

namespace App\Modules\Chief\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiefEdit extends Model
{
    protected $fillable = ['consolidation_id', 'block_id', 'chief_user_id', 'kind', 'before', 'after', 'before_hi', 'after_hi', 'audit_log_id'];

    public function consolidation(): BelongsTo
    {
        return $this->belongsTo(ChiefConsolidation::class, 'consolidation_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function chief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chief_user_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }
}
