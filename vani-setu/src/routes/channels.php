<?php

use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use App\Modules\Translator\Models\TranslatorAssignment;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('reporter.slot.{id}', function (User $user, int $id): bool {
    if ($user->isAdmin() || $user->isSupervisor()) {
        return Slot::query()->whereKey($id)->exists();
    }

    return $user->isReporter()
        && Slot::query()
            ->whereKey($id)
            ->whereHas('assignments', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
});

Broadcast::channel('translator.slot.{id}', function (User $user, int $id): bool {
    if ($user->isAdmin() || $user->isSupervisor() || $user->hasRole('director')) {
        return TranslatorAssignment::query()->where('slot_id', $id)->exists();
    }

    return $user->isTranslator()
        && TranslatorAssignment::query()
            ->where('slot_id', $id)
            ->where('translator_user_id', $user->id)
            ->whereIn('language_pair', $user->language_competencies ?? [])
            ->exists();
});

Broadcast::channel('translation.draft.{id}', function (User $user, int $id): bool {
    /** @var TranslatorAssignment|null $assignment */
    $assignment = TranslatorAssignment::query()->find($id);
    if (! $assignment) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    if ($user->isTranslator()) {
        return (int) $assignment->translator_user_id === (int) $user->id
            && in_array($assignment->language_pair, $user->language_competencies ?? [], true);
    }

    return ($user->isSupervisor() || $user->hasRole('director'))
        && translator_channel_has_language($user, $assignment->language_pair);
});

if (! function_exists('translator_channel_has_language')) {
    function translator_channel_has_language(User $user, string $languagePair): bool
    {
        $competencies = $user->language_competencies ?? [];
        $parts = explode('_to_', $languagePair);

        return in_array($languagePair, $competencies, true)
            || in_array($parts[0] ?? '', $competencies, true)
            || in_array($parts[1] ?? '', $competencies, true);
    }
}
