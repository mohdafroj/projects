<?php

namespace App\Modules\Js\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JsSgHandoff extends Model
{
    protected $fillable = [
        'window_id',
        'sent_at',
        'sent_audit_log_id',
        'returned_at',
        'returned_audit_log_id',
        'dsc_serial',
        'sg_user_id',
        'confirmed_expunges',
        'manual_expunges',
    ];

    protected $casts = ['sent_at' => 'datetime', 'returned_at' => 'datetime'];

    public function window(): BelongsTo
    {
        return $this->belongsTo(JsWindow::class, 'window_id');
    }

    public function sentAuditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'sent_audit_log_id');
    }

    public function returnedAuditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'returned_audit_log_id');
    }

    public function sgUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sg_user_id');
    }
}
