<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = true;

    protected $fillable = [
        'prev_hash',
        'this_hash',
        'actor_id',
        'actor_role',
        'chain_segment',
        'action',
        'subject_type',
        'subject_id',
        'payload',
        'request_ip',
        'request_ua',
        'request_id',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    public function save(array $options = []): bool
    {
        throw new RuntimeException('AuditLog is append-only. Use AuditLogger::log().');
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('AuditLog is append-only. Use AuditLogger::log().');
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('AuditLog is append-only. Use AuditLogger::log().');
    }
}
