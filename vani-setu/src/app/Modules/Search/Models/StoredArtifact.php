<?php

namespace App\Modules\Search\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoredArtifact extends Model
{
    protected $fillable = [
        'uuid',
        'title',
        'stored_disk',
        'storage_path',
        'storage_uri',
        'mime_type',
        'extension',
        'media_family',
        'sensitivity_classification',
        'source_system',
        'source_module',
        'subject_type',
        'subject_id',
        'uploaded_by_user_id',
        'size_bytes',
        'sha256',
        'tags',
        'metadata',
        'metadata_text',
        'search_text',
        'ai_eligible',
        'search_eligible',
        'classification_status',
        'search_status',
        'indexed_at',
        'last_hygiene_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'ai_eligible' => 'boolean',
        'search_eligible' => 'boolean',
        'indexed_at' => 'datetime',
        'last_hygiene_at' => 'datetime',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isNonSensitive(): bool
    {
        return $this->sensitivity_classification === 'non_sensitive';
    }
}
