<?php

namespace App\Modules\CommitteeSittings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Committee extends Model
{
    protected $fillable = ['code', 'name', 'type', 'terms_of_reference'];

    public function sittings(): HasMany
    {
        return $this->hasMany(CommitteeSitting::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CommitteeParticipant::class);
    }
}
