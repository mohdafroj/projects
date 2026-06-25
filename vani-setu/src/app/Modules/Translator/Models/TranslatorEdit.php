<?php

namespace App\Modules\Translator\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslatorEdit extends Model
{
    protected $fillable = [
        'assignment_id',
        'block_id',
        'kind',
        'ai_suggestion',
        'before',
        'after',
        'audit_log_id',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TranslatorAssignment::class, 'assignment_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }
}
