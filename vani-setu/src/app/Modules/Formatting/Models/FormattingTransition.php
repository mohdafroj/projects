<?php

namespace App\Modules\Formatting\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormattingTransition extends Model
{
    protected $fillable = [
        'job_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'audit_log_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(FormattingJob::class, 'job_id');
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
