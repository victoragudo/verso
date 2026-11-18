<?php

namespace App\Verso\Plugins\Slots;

use App\Models\User;
use App\Verso\Access\Support\Ability;
use App\Verso\Core\Enums\Capability;
use Closure;
use Illuminate\Http\Request;

// One component a plugin adds to an admin slot. The component key must be registered in the client registry.
final readonly class SlotItem
{
    /**
     * @param  array<string, mixed>|Closure(Request): (array<string, mixed>|null)  $props  data for the component; a closure that returns null hides the item
     */
    public function __construct(
        public AdminSlot $slot,
        public string $key,
        public string $component,
        public ?string $label = null,
        public Capability|string|Closure|null $capability = null,
        public int $order = 100,
        public array|Closure $props = [],
        public ?string $plugin = null,
    ) {}

    public function withPlugin(string $plugin): self
    {
        return new self($this->slot, $this->key, $this->component, $this->label, $this->capability, $this->order, $this->props, $plugin);
    }

    public function visibleTo(?User $user): bool
    {
        return $user !== null && Ability::allows($user, $this->capability);
    }

    /**
     * The props for this request, or null when the item is hidden.
     *
     * @return array<string, mixed>|null
     */
    public function resolveProps(Request $request): ?array
    {
        return $this->props instanceof Closure ? ($this->props)($request) : $this->props;
    }
}
