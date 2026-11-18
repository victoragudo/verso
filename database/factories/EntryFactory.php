<?php

namespace Database\Factories;

use App\Models\User;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    protected $model = Entry::class;

    /** @var list<string> */
    protected const TITLES = [
        'A Slow Weekend in Porto',
        'How We Bake Sourdough at Home',
        'Ten Lessons from a Year of Remote Work',
        'The Best Coffee Shops in Lisbon',
        'Why Our Garden Loves Tomatoes',
        'Notes from a Rainy Hike in the Pyrenees',
        'Choosing a Bike for City Commuting',
        'What I Learned Selling at the Farmers Market',
        'A Beginner Guide to Film Photography',
        'Cooking Paella for Twelve People',
        'Our First Month with Solar Panels',
        'Reading List for Long Winter Nights',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(self::TITLES).' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'type' => 'post',
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => EntryStatus::Draft,
            'blocks' => self::paragraphs(fake()->paragraphs(3)),
            'excerpt' => fake()->sentence(14),
            'author_id' => User::factory(),
            'parent_id' => null,
            'menu_order' => 0,
            'published_at' => null,
            'meta' => [],
        ];
    }

    /**
     * Turn plain text paragraphs into paragraph blocks.
     *
     * @param  array<int, string>|string  $paragraphs
     * @return list<array<string, mixed>>
     */
    public static function paragraphs(array|string $paragraphs): array
    {
        if (is_string($paragraphs)) {
            $paragraphs = preg_split('/\R{2,}/', $paragraphs) ?: [];
        }

        return array_values(array_map(fn (string $text): array => [
            'id' => 'b_'.Str::lower(Str::random(6)),
            'type' => 'paragraph',
            'attrs' => [],
            'content' => '<p>'.e($text).'</p>',
            'children' => [],
        ], $paragraphs));
    }

    public function page(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'page']);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntryStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 90)),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntryStatus::Scheduled,
            'published_at' => now()->addDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntryStatus::Trash,
            'deleted_at' => now(),
        ]);
    }

    public function by(User $author): static
    {
        return $this->state(fn (array $attributes) => ['author_id' => $author->id]);
    }
}
