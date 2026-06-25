<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCustom extends Model
{
    protected $fillable = ['slot_id', 'name_en', 'name_hi', 'role_title', 'state_jur', 'created_by_user_id'];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
