<?php

namespace App\Verso\Core\Admin;

use App\Models\User;
use App\Verso\Access\Support\Ability;
use App\Verso\Core\Enums\Capability;
use App\Verso\I18n\Translate;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// One link in the admin sidebar. An item with a parent is shown inside that item.
final readonly class NavItem
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<string>  $keywords  extra words (synonyms, other languages) that find the item in the search
     * @param  array<int, mixed>  $abilityArguments  arguments for a Gate ability, like [Entry::class, 'post']
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $route,
        public string $icon = 'dot',
        public int $order = 100,
        public Capability|string|Closure|null $capability = null,
        public array $parameters = [],
        public NavGroup $group = NavGroup::Content,
        public ?string $parent = null,
        public array $keywords = [],
        public ?string $plugin = null,
        public ?string $fragment = null,
        public array $abilityArguments = [],
    ) {}

    // True when the user may see this item: the item capability and the "can:" checks of its route.
    public function visibleTo(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        // A link to a page that does not exist (yet) is never shown.
        if (! $this->isGroupOnly() && ! Route::has($this->route)) {
            return false;
        }

        if (! Ability::allows($user, $this->capability, $this->abilityArguments)) {
            return false;
        }

        foreach ($this->routeAbilities() as $ability) {
            if (! Gate::forUser($user)->allows($ability)) {
                return false;
            }
        }

        return true;
    }

    // An item with no route only groups its children. It links to its first visible child.
    public function isGroupOnly(): bool
    {
        return $this->route === '';
    }

    // A copy that lives in another group and under another parent.
    public function placedIn(NavGroup $group, ?string $parent, ?string $plugin = null): self
    {
        return new self(
            $this->key, $this->label, $this->route, $this->icon, $this->order, $this->capability,
            $this->parameters, $group, $parent, $this->keywords, $plugin ?? $this->plugin, $this->fragment,
            $this->abilityArguments,
        );
    }

    // A copy with another parent, used when the parent is hidden for a user.
    public function withParent(?string $parent): self
    {
        return $this->placedIn($this->group, $parent);
    }

    // A copy with another label. The old label stays a search keyword, so the search still finds it.
    public function renamed(string $label): self
    {
        if ($label === $this->label) {
            return $this;
        }

        return new self(
            $this->key, $label, $this->route, $this->icon, $this->order, $this->capability, $this->parameters,
            $this->group, $this->parent, array_values(array_unique([...$this->keywords, $this->label])), $this->plugin, $this->fragment,
        );
    }

    // The link of the item, or null for an item that only groups others.
    public function href(): ?string
    {
        if ($this->isGroupOnly()) {
            return null;
        }

        $url = route($this->route, $this->parameters, false);

        return $this->fragment === null ? $url : $url.'#'.$this->fragment;
    }

    /**
     * The data sent to the admin UI.
     *
     * @return array{key: string, label: string, href: string, icon: string, group: string, parent: string|null, keywords: list<string>, plugin: string|null}
     */
    public function toArray(?string $href = null): array
    {
        $label = $this->translatedLabel();

        return [
            'key' => $this->key,
            'label' => $label,
            'href' => $href ?? (string) $this->href(),
            'icon' => $this->icon,
            'group' => $this->group->value,
            'parent' => $this->parent,
            'keywords' => $this->searchKeywords($label),
            'plugin' => $this->plugin,
        ];
    }

    public function translatedLabel(): string
    {
        return Translate::text($this->label, plugin: $this->plugin);
    }

    /**
     * @return list<string>
     */
    protected function searchKeywords(string $translatedLabel): array
    {
        $translated = array_map(fn (string $keyword): string => Translate::text($keyword, plugin: $this->plugin), $this->keywords);
        $source = $translatedLabel === $this->label ? $this->keywords : [$this->label, ...$this->keywords];

        return array_values(array_unique([...$translated, ...$source]));
    }

    /**
     * Abilities from "can:" middleware on the route, without route parameters.
     *
     * @return list<string>
     */
    protected function routeAbilities(): array
    {
        if ($this->isGroupOnly()) {
            return [];
        }

        $route = Route::getRoutes()->getByName($this->route);

        if ($route === null) {
            return [];
        }

        $abilities = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:') && ! str_contains($middleware, ',')) {
                $abilities[] = substr($middleware, 4);
            }
        }

        return $abilities;
    }
}
