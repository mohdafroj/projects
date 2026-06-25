<?php

namespace App\Modules\AdminFull\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminFullCustomMemberMaster extends Model
{
    protected $fillable = [
        'reference_code',
        'name_en',
        'name_hi',
        'role_title',
        'state_jur',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
