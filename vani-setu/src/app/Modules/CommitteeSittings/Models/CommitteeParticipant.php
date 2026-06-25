<?php

namespace App\Modules\CommitteeSittings\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeParticipant extends Model
{
    protected $fillable = ['committee_id', 'user_id', 'member_id', 'role'];
}
