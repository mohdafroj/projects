<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'roster_id',
        'category',
        'name_en',
        'name_hi',
        'party',
        'state_jur',
        'role_title',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
