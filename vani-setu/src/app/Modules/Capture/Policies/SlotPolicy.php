<?php

namespace App\Modules\Capture\Policies;

use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;

class SlotPolicy
{
    public function commit(User $user, Slot $slot, string $langRole): bool
    {
        return $slot->assignments()
            ->where('user_id', $user->id)
            ->where('lang_role', $langRole)
            ->where('status', '!=', 'committed')
            ->whereIn('workflow_stage', ['reporter', 'returned'])
            ->exists();
    }
}
