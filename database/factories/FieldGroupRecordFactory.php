<?php

namespace Database\Factories;

use App\Verso\Fields\Models\FieldGroupRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldGroupRecord>
 */
class FieldGroupRecordFactory extends Factory
{
    protected $model = FieldGroupRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'book_details_'.fake()->unique()->numberBetween(1, 99999),
            'title' => 'Book details',
            'description' => 'Facts shown in the book review sidebar.',
            'position' => 'main',
            'menu_order' => 0,
            'active' => true,
            'location' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'post']]],
            'fields' => [
                ['name' => 'book_author', 'label' => 'Book author', 'type' => 'text', 'required' => true],
                ['name' => 'pages', 'label' => 'Pages', 'type' => 'number', 'required' => false, 'min' => 1],
            ],
        ];
    }

    public function sidebar(): static
    {
        return $this->state(fn (array $attributes) => ['position' => 'sidebar']);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function withFields(array $fields): static
    {
        return $this->state(fn (array $attributes) => ['fields' => $fields]);
    }

    /**
     * @param  list<list<array{param: string, operator: string, value: string}>>  $location
     */
    public function at(array $location): static
    {
        return $this->state(fn (array $attributes) => ['location' => $location]);
    }
}
