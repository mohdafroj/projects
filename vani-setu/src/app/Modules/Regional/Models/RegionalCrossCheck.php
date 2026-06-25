<?php

namespace App\Modules\Regional\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionalCrossCheck extends Model
{
    protected $fillable = [
        'case_id',
        'reviewer_user_id',
        'result',
        'issues',
        'score',
        'notes',
        'audit_log_id',
    ];

    protected $casts = [
        'issues' => 'array',
        'score' => 'integer',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(RegionalCase::class, 'case_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }
}
