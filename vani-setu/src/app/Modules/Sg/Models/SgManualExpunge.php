<?php

namespace App\Modules\Sg\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\User;
use App\Modules\Js\Models\JsWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SgManualExpunge extends Model
{
    protected $fillable = [
        'window_id',
        'block_id',
        'word',
        'grounds',
        'added_by_sg_user_id',
        'audit_log_id',
    ];

    public function window(): BelongsTo
    {
        return $this->belongsTo(JsWindow::class, 'window_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function sgUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_sg_user_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }
}
