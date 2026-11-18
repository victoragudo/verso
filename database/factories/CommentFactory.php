<?php

namespace Database\Factories;

use App\Models\User;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Models\Entry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /** @var list<string> */
    protected const BODIES = [
        'Thanks for sharing this. We tried the recipe last Sunday and the kids loved it.',
        'I visited Porto in March and your list of cafes was spot on. The one near the river is my favourite now.',
        'Great point about remote work. Having a fixed end to the day changed everything for me.',
        'Do you have any tips for keeping tomatoes healthy when the summer is very dry?',
        'We switched to solar panels two years ago. The first winter surprised us too.',
        'Beautiful photos. Which film stock did you use for the night shots?',
        'This guide helped me choose my first commuter bike. A small chain guard makes a big difference.',
        'I have read three of the books on your list and agree with every word about the second one.',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory()->published(),
            'parent_id' => null,
            'user_id' => null,
            'author_name' => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'author_url' => null,
            'body' => fake()->randomElement(self::BODIES),
            'status' => CommentStatus::Pending,
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CommentStatus::Approved]);
    }

    public function spam(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CommentStatus::Spam]);
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CommentStatus::Trash]);
    }

    public function on(Entry $entry): static
    {
        return $this->state(fn (array $attributes) => ['entry_id' => $entry->id]);
    }

    public function replyTo(Comment $parent): static
    {
        return $this->state(fn (array $attributes) => ['entry_id' => $parent->entry_id, 'parent_id' => $parent->id]);
    }

    public function by(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'author_name' => $user->name,
            'author_email' => $user->email,
        ]);
    }
}
