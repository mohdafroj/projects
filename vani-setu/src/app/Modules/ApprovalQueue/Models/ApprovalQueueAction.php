<?php

namespace App\Modules\ApprovalQueue\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalQueueAction extends Model
{
    protected $fillable = [
        'user_id',
        'item_key',
        'module',
        'action',
        'note',
        'snoozed_until',
        'audit_log_id',
    ];

    protected $casts = [
        'snoozed_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }
}
