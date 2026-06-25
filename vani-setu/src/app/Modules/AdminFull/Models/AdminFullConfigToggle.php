<?php

namespace App\Modules\AdminFull\Models;

use Illuminate\Database\Eloquent\Model;

class AdminFullConfigToggle extends Model
{
    protected $fillable = ['key', 'enabled', 'value', 'description'];

    protected $casts = [
        'enabled' => 'boolean',
        'value' => 'array',
    ];
}
