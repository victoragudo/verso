<?php

namespace Database\Factories;

use App\Verso\Core\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(3);

        return [
            'from_path' => '/'.fake()->year().'/'.$slug,
            'to_path' => '/blog/'.$slug,
            'status_code' => 301,
        ];
    }

    public function temporary(): static
    {
        return $this->state(fn (array $attributes) => ['status_code' => 302]);
    }
}
