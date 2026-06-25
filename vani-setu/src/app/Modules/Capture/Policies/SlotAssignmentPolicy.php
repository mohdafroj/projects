<?php

namespace App\Modules\Capture\Policies;

use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;

class SlotAssignmentPolicy
{
    public function view(User $user, SlotAssignment $assignment): bool
    {
        return $this->isChiefOrAbove($user)
            || $assignment->user_id === $user->id
            || ($user->isSupervisor() && $this->hasLanguage($user, $assignment->lang_role));
    }

    public function forward(User $user, SlotAssignment $assignment): bool
    {
        return $user->isSupervisor()
            && $assignment->workflow_stage === 'supervisor'
            && $this->hasLanguage($user, $assignment->lang_role);
    }

    public function return(User $user, SlotAssignment $assignment): bool
    {
        return $this->forward($user, $assignment);
    }

    private function hasLanguage(User $user, string $langRole): bool
    {
        return in_array($langRole, $user->language_competencies ?? [], true);
    }

    private function isChiefOrAbove(User $user): bool
    {
        return $user->isChief() || $user->isJs() || $user->isSg() || $user->isAdmin();
    }
}
