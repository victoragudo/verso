<?php

namespace Database\Factories;

use App\Verso\Site\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /** @var list<string> */
    protected const NAMES = ['Main navigation', 'Footer links', 'Recipe sections', 'Club pages', 'Travel guides'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(self::NAMES),
        ];
    }

    public function at(string $location): static
    {
        return $this->state(fn (array $attributes) => ['location' => $location]);
    }
}
