<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Slot extends Model
{
    protected $fillable = ['sitting_id', 'code', 'start_offset_ms', 'duration_ms', 'topic', 'status'];

    public function sitting(): BelongsTo
    {
        return $this->belongsTo(Sitting::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('sequence');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SlotAssignment::class);
    }

    public function overallWorkflowStage(): string
    {
        $rank = [
            'reporter' => 0,
            'returned' => 1,
            'supervisor' => 2,
            'chief' => 3,
        ];

        return $this->assignments()
            ->pluck('workflow_stage')
            ->sortBy(fn (string $stage) => $rank[$stage] ?? PHP_INT_MAX)
            ->first() ?? 'reporter';
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->join('sittings', 'sittings.id', '=', 'slots.sitting_id')
            ->where('sittings.status', 'live')
            ->select('slots.*');
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->join('slot_assignments', 'slot_assignments.slot_id', '=', 'slots.id')
            ->where('slot_assignments.user_id', $user->id)
            ->select('slots.*');
    }
}
