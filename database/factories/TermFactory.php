<?php

namespace Database\Factories;

use App\Verso\Content\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    /** @var list<string> */
    protected const NAMES = ['Travel', 'Food', 'Gardening', 'Photography', 'Remote Work', 'Cycling', 'Books', 'Home Energy'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(self::NAMES).' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'taxonomy' => 'category',
            'name' => $name,
            'slug' => Str::slug($name),
            'parent_id' => null,
            'description' => fake()->sentence(10),
        ];
    }

    public function tag(): static
    {
        return $this->state(fn (array $attributes) => ['taxonomy' => 'tag']);
    }
}
