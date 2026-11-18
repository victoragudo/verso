<?php

namespace Database\Factories;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Revision>
 */
class RevisionFactory extends Factory
{
    protected $model = Revision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory(),
            'user_id' => null,
            'title' => 'Draft: '.fake()->sentence(5),
            'blocks' => EntryFactory::paragraphs(fake()->paragraphs(2)),
            'excerpt' => fake()->sentence(12),
            'meta' => [],
        ];
    }
}
