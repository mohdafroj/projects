<?php

namespace App\Modules\Js\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JsDecision extends Model
{
    protected $fillable = ['window_id', 'kind', 'actor_id', 'payload', 'audit_log_id'];

    protected $casts = ['payload' => 'array'];

    public function window(): BelongsTo
    {
        return $this->belongsTo(JsWindow::class, 'window_id');
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
