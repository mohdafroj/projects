<?php

namespace App\Modules\Translator\Models;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranslatorAssignment extends Model
{
    protected $fillable = [
        'sitting_id',
        'slot_id',
        'window_id',
        'translator_user_id',
        'language_pair',
        'status',
        'ai_translation_meta',
    ];

    protected $casts = [
        'ai_translation_meta' => 'array',
    ];

    public function sitting(): BelongsTo
    {
        return $this->belongsTo(Sitting::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function translator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'translator_user_id');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(TranslatorEdit::class, 'assignment_id');
    }

    public function commits(): HasMany
    {
        return $this->hasMany(TranslatorCommit::class, 'assignment_id');
    }

    public function blocks()
    {
        return Block::query()->where('slot_id', $this->slot_id)->orderBy('sequence');
    }

    public function isReady(): bool
    {
        return in_array($this->status, ['open', 'in_review', 'returned'], true) && $this->slot_id !== null;
    }

    public function aiAcceptanceRate(): float
    {
        $total = $this->edits()->count();
        if ($total === 0) {
            return 0.0;
        }

        $accepted = $this->edits()->where('kind', 'text')->whereColumn('ai_suggestion', 'after')->count();

        return round(($accepted / $total) * 100, 2);
    }

    public function wordsTranslated(): int
    {
        return $this->blocks()->get()->sum(function (Block $block): int {
            return str_word_count(strip_tags((string) ($block->translated_text ?: $block->text)));
        });
    }
}
