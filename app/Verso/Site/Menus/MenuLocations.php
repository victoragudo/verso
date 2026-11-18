<?php

namespace App\Verso\Site\Menus;

use App\Verso\I18n\Translate;
use Closure;

// The places where a theme can show a menu, like "primary" or "footer". Themes and plugins can add more.
class MenuLocations
{
    /** @var array<string, string> name => label */
    protected array $locations = [];

    /** @var list<Closure(): array<string, string>> lists read the first time locations are needed, like the active theme menus */
    protected array $deferred = [];

    /** @var array<string, Closure(string): string> */
    protected array $translators = [];

    /**
     * @param  array<string, string>  $locations
     */
    public function __construct(array $locations = [])
    {
        foreach ($locations as $name => $label) {
            $this->register($name, $label);
        }
    }

    public function register(string $name, string $label): void
    {
        $this->locations[$name] = $label;
    }

    // Adds locations later, the first time they are needed. Themes use it, so no database read happens at boot.
    /**
     * @param  Closure(): array<string, string>  $locations
     * @param  (Closure(string): string)|null  $translate
     */
    public function defer(Closure $locations, ?Closure $translate = null): void
    {
        $this->deferred[] = $translate === null ? $locations : function () use ($locations, $translate): array {
            $added = $locations();

            foreach (array_keys($added) as $name) {
                if (! isset($this->locations[$name])) {
                    $this->translators[$name] ??= $translate;
                }
            }

            return $added;
        };
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function label(string $name): ?string
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        while (($next = array_shift($this->deferred)) !== null) {
            foreach ($next() as $name => $label) {
                $this->locations[$name] ??= $label;
            }
        }

        return $this->locations;
    }

    /**
     * For the admin UI.
     *
     * @return list<array{name: string, label: string}>
     */
    public function toList(): array
    {
        $list = [];

        foreach ($this->translated() as $name => $label) {
            $list[] = ['name' => $name, 'label' => $label];
        }

        return $list;
    }

    /** @return array<string, string> */
    public function translated(): array
    {
        $translated = [];

        foreach ($this->all() as $name => $label) {
            $translated[$name] = isset($this->translators[$name]) ? ($this->translators[$name])($label) : Translate::text($label);
        }

        return $translated;
    }
}
