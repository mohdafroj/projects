<?php

namespace App\Modules\Capture\Policies;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\User;

class BlockPolicy
{
    public function update(User $user, Block $block): bool
    {
        return $block->slot->assignments()
            ->where('user_id', $user->id)
            ->where('lang_role', $block->original_lang)
            ->where('status', '!=', 'committed')
            ->whereIn('workflow_stage', ['reporter', 'returned'])
            ->exists();
    }
}
