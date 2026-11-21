<?php

namespace App\Verso\Core\Admin;

use App\Models\User;
use App\Verso\Access\Support\Ability;
use App\Verso\Core\Enums\Capability;
use App\Verso\I18n\Translate;
use Closure;

// A short list on the admin dashboard, like "Recent entries".
final readonly class DashboardPanel
{
    /**
     * @param  Closure(User): list<array{key: string, title: string, meta: string|null, href: string|null, status: string|null}>  $items
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public string $key,
        public string $label,
        public Closure $items,
        public string $icon = 'dot',
        public string $emptyText = '',
        public int $order = 100,
        public Capability|string|Closure|null $capability = null,
        public ?string $route = null,
        public array $parameters = [],
        public ?string $plugin = null,
    ) {}

    public function visibleTo(User $user): bool
    {
        return Ability::allows($user, $this->capability);
    }

    /**
     * The data sent to the dashboard page.
     *
     * @return array{key: string, label: string, icon: string, emptyText: string, href: string|null, items: list<array{key: string, title: string, meta: string|null, href: string|null, status: string|null}>}
     */
    public function toArray(User $user): array
    {
        return [
            'key' => $this->key,
            'label' => Translate::text($this->label, plugin: $this->plugin),
            'icon' => $this->icon,
            'emptyText' => $this->emptyText === '' ? __('Nothing here yet.') : Translate::text($this->emptyText, plugin: $this->plugin),
            'href' => $this->route === null ? null : route($this->route, $this->parameters, false),
            'items' => ($this->items)($user),
        ];
    }
}
