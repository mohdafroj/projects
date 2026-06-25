<?php

namespace App\Modules\Chief\Models;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\SlotAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChiefConsolidation extends Model
{
    protected $fillable = ['sitting_id', 'window_code', 'starts_at_offset_ms', 'duration_ms', 'status'];

    public function sitting(): BelongsTo
    {
        return $this->belongsTo(Sitting::class);
    }

    public function commits(): HasMany
    {
        return $this->hasMany(ChiefConsolidationCommit::class, 'consolidation_id');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(ChiefEdit::class, 'consolidation_id')->latest();
    }

    public function speakerOverrides(): HasMany
    {
        return $this->hasMany(ChiefSpeakerOverride::class, 'consolidation_id');
    }

    public function bothCommitted(): bool
    {
        $langs = $this->commits()->pluck('lang_side')->all();

        return in_array('en', $langs, true) && in_array('hi', $langs, true);
    }

    public function inputsReady(): bool
    {
        $slotIds = $this->slotIdsInWindow();

        if (count($slotIds) < 6) {
            return false;
        }

        return SlotAssignment::query()
            ->whereIn('slot_id', $slotIds)
            ->where('workflow_stage', 'chief')
            ->distinct('slot_id')
            ->count('slot_id') >= 6;
    }

    public function blocks()
    {
        return Block::query()
            ->whereHas('slot', fn ($query) => $query
                ->where('sitting_id', $this->sitting_id)
                ->where('start_offset_ms', '>=', $this->starts_at_offset_ms)
                ->where('start_offset_ms', '<', $this->starts_at_offset_ms + $this->duration_ms))
            ->orderBy('slot_id')
            ->orderBy('sequence');
    }

    /**
     * @return list<int>
     */
    public function slotIdsInWindow(): array
    {
        return $this->sitting->slots()
            ->where('start_offset_ms', '>=', $this->starts_at_offset_ms)
            ->where('start_offset_ms', '<', $this->starts_at_offset_ms + $this->duration_ms)
            ->orderBy('start_offset_ms')
            ->limit(6)
            ->pluck('id')
            ->all();
    }
}
