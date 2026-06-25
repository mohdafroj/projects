<?php

namespace App\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationDispatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'channel',
        'status',
        'producer',
        'recipients',
        'subject',
        'body',
        'template_id',
        'idempotency_key',
        'metadata',
        'provider_response',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'metadata' => 'array',
        'provider_response' => 'array',
        'sent_at' => 'datetime',
    ];
}
