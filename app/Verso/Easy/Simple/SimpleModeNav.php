<?php

namespace App\Verso\Easy\Simple;

use App\Verso\Core\Admin\NavItem;

// The sidebar items simple mode keeps, and plain names for some of them. Plugins add their keys in boot().
class SimpleModeNav
{
    /** @var array<string, true> */
    protected array $keys = [];

    /** @var array<string, string> plain label by nav key */
    protected array $labels = [];

    // Keep these items in simple mode. A child item also needs its key; a hidden parent moves it up.
    public function allow(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->keys[$key] = true;
        }
    }

    public function disallow(string $key): void
    {
        unset($this->keys[$key], $this->labels[$key]);
    }

    public function allows(string $key): bool
    {
        return isset($this->keys[$key]);
    }

    // Show the item with this plain name in simple mode. It also allows the item.
    public function rename(string $key, string $label): void
    {
        $this->allow($key);
        $this->labels[$key] = $label;
    }

    public function label(string $key): ?string
    {
        return $this->labels[$key] ?? null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->keys);
    }

    /**
     * Only the allowed items, with their plain names. Anything that is not a NavItem is left out.
     *
     * @param  array<mixed>  $items
     * @return list<NavItem>
     */
    public function filter(array $items): array
    {
        $kept = [];

        foreach ($items as $item) {
            if ($item instanceof NavItem && $this->allows($item->key)) {
                $label = $this->label($item->key);
                $kept[] = $label === null ? $item : $item->renamed($label);
            }
        }

        return $kept;
    }
}
