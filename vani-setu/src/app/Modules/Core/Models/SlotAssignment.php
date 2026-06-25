<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlotAssignment extends Model
{
    protected $fillable = [
        'slot_id',
        'user_id',
        'assignee_user_id',
        'lang_role',
        'status',
        'workflow_stage',
        'committed_at',
        'committed_audit_log_id',
        'last_workflow_action_at',
    ];

    protected $casts = [
        'committed_at' => 'datetime',
        'last_workflow_action_at' => 'datetime',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function workflowEvents(): HasMany
    {
        return $this->hasMany(SlotWorkflowEvent::class)->latest('created_at');
    }
}
