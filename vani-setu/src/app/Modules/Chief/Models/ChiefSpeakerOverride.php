<?php

namespace App\Modules\Chief\Models;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\MemberCustom;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiefSpeakerOverride extends Model
{
    protected $fillable = [
        'consolidation_id',
        'block_id',
        'reporter_member_id',
        'chief_member_id',
        'chief_custom_member_id',
        'chief_user_id',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function reporterMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reporter_member_id');
    }

    public function chiefMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'chief_member_id');
    }

    public function chiefCustomMember(): BelongsTo
    {
        return $this->belongsTo(MemberCustom::class, 'chief_custom_member_id');
    }

    public function chief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chief_user_id');
    }
}
