<?php

namespace App\Modules\Reports\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSnapshot extends Model
{
    protected $fillable = [
        'name',
        'filters',
        'chart_data',
        'export_meta',
        'captured_by_user_id',
        'captured_audit_log_id',
        'captured_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'chart_data' => 'array',
        'export_meta' => 'array',
        'captured_at' => 'datetime',
    ];

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function capturedAuditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'captured_audit_log_id');
    }
}
