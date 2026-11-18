<?php

namespace App\Verso\Core\Admin;

use App\Models\User;
use App\Verso\Access\Support\Ability;
use App\Verso\Core\Enums\Capability;
use App\Verso\I18n\Translate;
use Closure;

// A number shown on the admin dashboard, like "Published posts".
final readonly class DashboardStat
{
    /**
     * @param  Closure(): int  $count
     * @param  array<string, mixed>  $parameters
     * @param  string|null  $icon  key from the Verso icon set, like "posts" or "comments"
     * @param  array<string, string|Closure(): string>  $labelReplace
     */
    public function __construct(
        public string $key,
        public string $label,
        public Closure $count,
        public int $order = 100,
        public Capability|string|Closure|null $capability = null,
        public ?string $route = null,
        public array $parameters = [],
        public ?string $icon = null,
        public ?string $plugin = null,
        public array $labelReplace = [],
    ) {}

    public function visibleTo(User $user): bool
    {
        return Ability::allows($user, $this->capability);
    }

    /**
     * @return array{key: string, label: string, value: int, href: string|null, icon: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => Translate::text($this->label, $this->resolvedLabelReplace(), $this->plugin),
            'value' => ($this->count)(),
            'href' => $this->route === null ? null : route($this->route, $this->parameters, false),
            'icon' => $this->icon,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function resolvedLabelReplace(): array
    {
        return array_map(fn (string|Closure $value): string => $value instanceof Closure ? $value() : $value, $this->labelReplace);
    }
}
