<?php

namespace App\Modules\Js\Models;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestedEdit extends Model
{
    protected $fillable = ['window_id', 'source', 'source_name', 'source_member_id', 'block_id', 'before', 'after', 'reason', 'state'];

    public function window(): BelongsTo
    {
        return $this->belongsTo(JsWindow::class, 'window_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function sourceMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'source_member_id');
    }
}
