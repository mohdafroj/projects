<?php

namespace App\Modules\CommitteeSittings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitteeDocument extends Model
{
    protected $fillable = [
        'committee_sitting_id',
        'chair_user_id',
        'prepared_by_user_id',
        'document_type',
        'status',
        'title',
        'body',
        'in_camera',
        'chair_signed_at',
        'laid_at',
        'prism_archive_ref',
        'dsc_serial',
    ];

    protected $casts = [
        'in_camera' => 'boolean',
        'chair_signed_at' => 'datetime',
        'laid_at' => 'datetime',
    ];

    public function committeeSitting(): BelongsTo
    {
        return $this->belongsTo(CommitteeSitting::class);
    }
}
