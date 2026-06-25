<?php

namespace App\Modules\AdminFull\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminFullSittingTemplate extends Model
{
    protected $fillable = ['name', 'session_no', 'default_status', 'metadata', 'is_active'];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function slotTemplates(): HasMany
    {
        return $this->hasMany(AdminFullSlotTemplate::class, 'sitting_template_id');
    }
}
