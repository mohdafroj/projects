<?php

namespace App\Modules\Regional\Services;

use App\Modules\Core\Models\User;

class RegionalRoutingService
{
    public function specialistFor(string $sourceLanguage, ?User $exclude = null): ?User
    {
        return User::role('translator')
            ->where('is_active', true)
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude->id))
            ->get()
            ->first(fn (User $user) => in_array($sourceLanguage.'_to_hi', $user->language_competencies ?? [], true));
    }
}
