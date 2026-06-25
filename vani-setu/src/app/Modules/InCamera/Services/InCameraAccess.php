<?php

namespace App\Modules\InCamera\Services;

use App\Modules\CommitteeSittings\Models\CommitteeParticipant;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\User;

class InCameraAccess
{
    public function canViewBlock(User $user, Block $block): bool
    {
        if (! (bool) $block->getAttribute('in_camera_flag')) {
            return true;
        }

        $committeeId = $block->getAttribute('committee_id');
        if (! $committeeId) {
            return $user->hasAnyRole(['admin', 'committee_chair', 'committee_secretary', 'committee_secretariat']);
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return CommitteeParticipant::query()
            ->where('committee_id', $committeeId)
            ->where('user_id', $user->id)
            ->whereIn('role', ['committee_chair', 'committee_secretary', 'committee_secretariat'])
            ->exists();
    }

    public function redactedText(User $user, Block $block): ?string
    {
        return $this->canViewBlock($user, $block) ? $block->text : '[IN-CAMERA REDACTED]';
    }
}
