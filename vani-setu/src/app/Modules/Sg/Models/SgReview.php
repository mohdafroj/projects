<?php

namespace App\Modules\Sg\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use App\Modules\Js\Models\JsWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SgReview extends Model
{
    protected $fillable = [
        'window_id',
        'sg_user_id',
        'opened_at',
        'signed_at',
        'dsc_serial',
        'confirmed_expunges',
        'overridden_expunges',
        'manual_expunges',
        'audit_log_id_open',
        'audit_log_id_sign',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function window(): BelongsTo
    {
        return $this->belongsTo(JsWindow::class, 'window_id');
    }

    public function sgUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sg_user_id');
    }

    public function openAuditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'audit_log_id_open');
    }

    public function signAuditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'audit_log_id_sign');
    }
}
