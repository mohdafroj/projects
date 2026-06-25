<?php

namespace App\Modules\Js\Models;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JsWindow extends Model
{
    protected $fillable = ['sitting_id', 'window_code', 'starts_at_offset_ms', 'duration_ms', 'status'];

    public function sitting(): BelongsTo
    {
        return $this->belongsTo(Sitting::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(JsDecision::class, 'window_id')->latest();
    }

    public function handoffs(): HasMany
    {
        return $this->hasMany(JsSgHandoff::class, 'window_id')->latest();
    }

    public function suggestedEdits(): HasMany
    {
        return $this->hasMany(SuggestedEdit::class, 'window_id');
    }

    public function expungeCandidates(): HasMany
    {
        return $this->hasMany(ExpungeCandidate::class, 'window_id');
    }

    public function bothChiefHalves()
    {
        return ChiefConsolidation::query()
            ->where('sitting_id', $this->sitting_id)
            ->where('starts_at_offset_ms', '>=', $this->starts_at_offset_ms)
            ->where('starts_at_offset_ms', '<', $this->starts_at_offset_ms + $this->duration_ms)
            ->where('duration_ms', 1800000)
            ->orderBy('starts_at_offset_ms');
    }

    public function suggestedEditsCount(?string $state = null): int
    {
        return $this->suggestedEdits()->when($state, fn ($query) => $query->where('state', $state))->count();
    }

    public function expungeCandidatesCount(?string $state = null): int
    {
        return $this->expungeCandidates()->when($state, fn ($query) => $query->where('state', $state))->count();
    }

    public function blocks()
    {
        return Block::query()
            ->whereHas('slot', fn ($query) => $query
                ->where('sitting_id', $this->sitting_id)
                ->where('start_offset_ms', '>=', $this->starts_at_offset_ms)
                ->where('start_offset_ms', '<', $this->starts_at_offset_ms + $this->duration_ms))
            ->with(['member', 'customMember'])
            ->orderBy('slot_id')
            ->orderBy('sequence');
    }
}
