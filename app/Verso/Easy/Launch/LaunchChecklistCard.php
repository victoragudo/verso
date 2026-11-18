<?php

namespace App\Verso\Easy\Launch;

use App\Models\User;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Enums\Capability;
use App\Verso\Easy\Simple\SimpleMode;
use App\Verso\Easy\Support\EasyState;

// Decides when the dashboard shows the launch checklist, and what it shows.
class LaunchChecklistCard
{
    // Key of the card in the list of closed cards of a person.
    public const KEY = 'launch-checklist';

    public function __construct(
        protected LaunchChecklist $checklist,
        protected SimpleMode $simpleMode,
        protected EasyState $state,
    ) {}

    // People who manage the settings see it in simple mode or after a starter site, until they close it.
    public function visibleTo(User $user): bool
    {
        return $user->hasCapability(Capability::ManageSettings)
            && ! AdminPreferences::of($user)->hasDismissed(self::KEY)
            && ($this->simpleMode->isOn($user) || $this->state->hasStarter());
    }

    /**
     * The card props, or null when the card is hidden.
     *
     * @return array{items: list<array{key: string, label: string, description: string, href: string, action: string, method: string, done: bool}>, done: int, total: int, dismissUrl: string}|null
     */
    public function props(User $user): ?array
    {
        if (! $this->visibleTo($user)) {
            return null;
        }

        $list = $this->checklist->toArray();

        return $list['total'] === 0 ? null : [...$list, 'dismissUrl' => route('admin.easy.launch.dismiss', absolute: false)];
    }

    // Closes the card for this person. Only possible once every step is done.
    public function dismiss(User $user): bool
    {
        if (! $this->checklist->isComplete()) {
            return false;
        }

        AdminPreferences::of($user)->dismiss(self::KEY);

        return true;
    }
}
