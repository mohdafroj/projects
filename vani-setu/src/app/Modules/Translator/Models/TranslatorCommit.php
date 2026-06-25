<?php

namespace App\Modules\Translator\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslatorCommit extends Model
{
    protected $fillable = [
        'assignment_id',
        'translator_user_id',
        'block_count',
        'edit_count',
        'ai_acceptance_rate',
        'committed_at',
        'committed_audit_log_id',
    ];

    protected $casts = [
        'committed_at' => 'datetime',
        'ai_acceptance_rate' => 'decimal:2',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TranslatorAssignment::class, 'assignment_id');
    }

    public function translator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'translator_user_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'committed_audit_log_id');
    }
}
