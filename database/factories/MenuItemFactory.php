<?php

namespace Database\Factories;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Site\Enums\MenuItemType;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    /** @var array<string, string> */
    protected const LINKS = [
        'Contact' => '/contact',
        'Newsletter' => 'https://letters.example.org/signup',
        'Shop' => 'https://market.example.org',
        'Events' => '/events',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->randomElement(array_keys(self::LINKS));

        return [
            'menu_id' => Menu::factory(),
            'parent_id' => null,
            'type' => MenuItemType::Custom,
            'label' => $label,
            'url' => self::LINKS[$label],
            'open_in_new_tab' => false,
            'position' => 0,
        ];
    }

    public function forEntry(Entry $entry): static
    {
        return $this->state(fn (array $attributes) => ['type' => MenuItemType::Entry, 'entry_id' => $entry->id, 'label' => null, 'url' => null]);
    }

    public function forTerm(Term $term): static
    {
        return $this->state(fn (array $attributes) => ['type' => MenuItemType::Term, 'term_id' => $term->id, 'label' => null, 'url' => null]);
    }

    public function under(MenuItem $parent): static
    {
        return $this->state(fn (array $attributes) => ['menu_id' => $parent->menu_id, 'parent_id' => $parent->id]);
    }
}
