<?php

namespace App\Verso\Site\Models;

use App\Verso\Site\Menus\MenuLocations;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * A navigation menu with nested items. It can be shown in one theme location.
 *
 * @property int $id
 * @property string $name
 * @property string|null $location
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, MenuItem> $items
 */
#[Fillable(['name'])]
#[UseFactory(MenuFactory::class)]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    /** @return HasMany<MenuItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('position')->orderBy('id');
    }

    // The menu shown in a theme location, if any.
    public static function forLocation(string $location): ?self
    {
        return self::query()->where('location', $location)->first();
    }

    // Show this menu in a location (or nowhere with null). Another menu in that location is moved out.
    public function assignLocation(?string $location): static
    {
        if ($location !== null && ! app(MenuLocations::class)->has($location)) {
            throw new InvalidArgumentException("Unknown menu location [{$location}].");
        }

        if ($location !== null) {
            self::query()->where('location', $location)->whereKeyNot($this->getKey())->update(['location' => null]);
        }

        $this->location = $location;
        $this->save();

        return $this;
    }

    /**
     * The top level items with their children loaded as a tree, using one query.
     *
     * @param  list<string>  $with  relations to eager load on every item
     * @return Collection<int, MenuItem>
     */
    public function tree(array $with = []): Collection
    {
        $items = $this->items()->with($with)->get();

        /** @var array<int, list<MenuItem>> $byParent */
        $byParent = [];
        foreach ($items as $item) {
            $byParent[$item->parent_id ?? 0][] = $item;
        }

        foreach ($items as $item) {
            $item->setRelation('children', new Collection($byParent[$item->id] ?? []));
        }

        return new Collection($byParent[0] ?? []);
    }

    // How deep the item tree goes (0 when empty, 1 when there are no sub items).
    public function depth(): int
    {
        return $this->measureDepth($this->tree());
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     */
    protected function measureDepth(Collection $items): int
    {
        $deepest = 0;

        foreach ($items as $item) {
            $deepest = max($deepest, 1 + $this->measureDepth($item->children));
        }

        return $deepest;
    }
}
