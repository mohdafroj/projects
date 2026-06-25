<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotWorkflowEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slot_assignment_id',
        'from_stage',
        'to_stage',
        'action',
        'actor_id',
        'actor_role',
        'reason',
        'audit_log_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function slotAssignment(): BelongsTo
    {
        return $this->belongsTo(SlotAssignment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }
}
