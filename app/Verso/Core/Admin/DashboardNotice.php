<?php

namespace App\Verso\Core\Admin;

use App\Models\User;
use App\Verso\Access\Support\Ability;
use App\Verso\Core\Enums\Capability;
use Closure;

// A message box at the top of the dashboard, like "Verso 1.2.0 is available".
final readonly class DashboardNotice
{
    /**
     * @param  Closure(User): (array{title: string, description: string|null, href: string|null, action: string|null, tone: string}|null)  $resolve
     */
    public function __construct(
        public string $key,
        public Closure $resolve,
        public Capability|string|Closure|null $capability = null,
        public int $order = 100,
        public ?string $icon = null,
    ) {}

    public function visibleTo(User $user): bool
    {
        return Ability::allows($user, $this->capability);
    }

    /**
     * The notice data, or null when there is nothing to say right now.
     *
     * @return array{key: string, icon: string|null, title: string, description: string|null, href: string|null, action: string|null, tone: string}|null
     */
    public function toArray(User $user): ?array
    {
        $data = ($this->resolve)($user);

        return $data === null ? null : ['key' => $this->key, 'icon' => $this->icon, ...$data];
    }
}
