<?php

namespace App\Modules\Director\Models;

use App\Modules\Core\Models\User;
use App\Modules\Js\Models\JsWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectorPublishJob extends Model
{
    protected $fillable = [
        'window_id',
        'director_user_id',
        'queued_at',
        'ran_at',
        'finished_at',
        'status',
        'crc_pdf_path',
        'sansad_url',
        'last_error',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'ran_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function window(): BelongsTo
    {
        return $this->belongsTo(JsWindow::class, 'window_id');
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_user_id');
    }
}
