<?php

namespace App\Modules\Synopsis\Models;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SynopsisDocumentEdit extends Model
{
    protected $fillable = [
        'synopsis_document_id',
        'writer_user_id',
        'kind',
        'from_version',
        'to_version',
        'before_excerpt',
        'after_excerpt',
        'audit_log_id',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SynopsisDocument::class, 'synopsis_document_id');
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'writer_user_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }
}
