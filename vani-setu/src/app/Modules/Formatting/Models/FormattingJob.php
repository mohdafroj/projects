<?php

namespace App\Modules\Formatting\Models;

use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\User;
use App\Modules\Js\Models\JsWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormattingJob extends Model
{
    protected $fillable = [
        'window_id',
        'sitting_id',
        'formatter_user_id',
        'artifact_type',
        'status',
        'metadata',
        'policy_report',
        'crc_source_hash',
        'page_count',
        'crc_path',
        'created_audit_log_id',
        'validated_audit_log_id',
        'crc_audit_log_id',
        'dispatched_audit_log_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'policy_report' => 'array',
    ];

    public function sitting(): BelongsTo
    {
        return $this->belongsTo(Sitting::class);
    }

    public function window(): BelongsTo
    {
        return $this->belongsTo(JsWindow::class, 'window_id');
    }

    public function formatter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formatter_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FormattingLine::class, 'job_id')->orderBy('sequence');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(FormattingTransition::class, 'job_id')->latest();
    }
}
