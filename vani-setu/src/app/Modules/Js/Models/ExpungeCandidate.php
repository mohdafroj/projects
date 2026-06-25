<?php

namespace App\Modules\Js\Models;

use App\Modules\Core\Models\Block;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpungeCandidate extends Model
{
    protected $fillable = ['window_id', 'block_id', 'word', 'grounds', 'master_db_ref', 'state'];

    public function window(): BelongsTo
    {
        return $this->belongsTo(JsWindow::class, 'window_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}
