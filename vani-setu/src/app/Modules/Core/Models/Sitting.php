<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sitting extends Model
{
    protected $fillable = ['session_no', 'sitting_no', 'sitting_date', 'status', 'started_at', 'ended_at'];

    protected $casts = [
        'sitting_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }
}
