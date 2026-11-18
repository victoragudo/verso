<?php

namespace Database\Factories;

use App\Models\User;
use App\Verso\Media\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /** @var list<string> */
    protected const NAMES = [
        'Sunset over the Douro',
        'Sourdough loaf on the counter',
        'Morning market in Lisbon',
        'Tomato plants in July',
        'Bike parked by the canal',
        'Snow on the Pyrenees',
        'Paella pan on the fire',
        'Solar panels on the roof',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(self::NAMES);
        $file = Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999).'.jpg';

        return [
            'disk' => (string) config('verso.media.disk'),
            'path' => now()->format('Y/m').'/'.$file,
            'filename' => $file,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(80_000, 2_400_000),
            'width' => 1600,
            'height' => 1067,
            'alt' => $name,
            'caption' => null,
            'uploaded_by' => User::factory(),
            'meta' => [],
        ];
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => now()->format('Y/m').'/garden-tour-'.fake()->unique()->numberBetween(1, 99999).'.mp4',
            'filename' => 'garden-tour.mp4',
            'mime_type' => 'video/mp4',
            'width' => null,
            'height' => null,
            'alt' => null,
        ]);
    }

    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => now()->format('Y/m').'/bakery-price-list-'.fake()->unique()->numberBetween(1, 99999).'.pdf',
            'filename' => 'bakery-price-list.pdf',
            'mime_type' => 'application/pdf',
            'width' => null,
            'height' => null,
            'alt' => null,
        ]);
    }

    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => now()->format('Y/m').'/podcast-episode-'.fake()->unique()->numberBetween(1, 99999).'.mp3',
            'filename' => 'podcast-episode.mp3',
            'mime_type' => 'audio/mpeg',
            'width' => null,
            'height' => null,
            'alt' => null,
        ]);
    }
}
