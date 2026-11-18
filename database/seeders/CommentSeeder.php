<?php

namespace Database\Seeders;

use App\Models\User;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Models\Entry;
use Illuminate\Database\Seeder;

// Seeds approved and pending comments, with one reply from the site team. Posts that already have comments are skipped.
class CommentSeeder extends Seeder
{
    /**
     * Post slug => list of [name, email, text, status, hours after the post].
     *
     * @var array<string, list<array{0: string, 1: string, 2: string, 3: CommentStatus, 4: int}>>
     */
    protected const COMMENTS = [
        'a-slow-weekend-in-porto' => [
            ['Joana Ribeiro', 'joana.ribeiro@example.pt', 'Lovely post! Next time try the francesinha at Café Santiago, it is worth the queue.', CommentStatus::Approved, 5],
            ['Tom Whitaker', 'tom.whitaker@example.co.uk', 'We did the same bridge walk in May. Did you take the cable car down to the river?', CommentStatus::Approved, 30],
            ['Marco Bellini', 'marco.bellini@example.it', 'Which cellar in Gaia would you recommend for a first visit?', CommentStatus::Pending, 50],
        ],
        'how-we-bake-sourdough-at-home' => [
            ['Sofia Almeida', 'sofia.almeida@example.pt', 'The cold rise made all the difference for me too. My crumb finally opened up.', CommentStatus::Approved, 12],
            ['Daniel Kraus', 'daniel.kraus@example.de', 'How much starter do you use for one loaf? Mine always comes out a bit flat.', CommentStatus::Pending, 40],
        ],
        'the-best-coffee-shops-in-lisbon' => [
            ['Ana Costa', 'ana.costa@example.pt', 'The roaster in Principe Real is my Thursday ritual. Great list!', CommentStatus::Approved, 8],
        ],
        'composting-on-a-fourth-floor-balcony' => [
            ['Henrik Lindqvist', 'henrik.lindqvist@example.se', 'We started a worm box in our flat in Malmö after reading this. Zero smell, happy worms, very proud children.', CommentStatus::Approved, 20],
        ],
        'five-essays-that-changed-how-i-rest' => [
            ['Mariana Duarte', 'mariana.duarte@example.br', 'Pieper was new to me. I read it on a Sunday afternoon, as you suggested, and then I took a very long walk.', CommentStatus::Approved, 30],
        ],
        'cooking-paella-for-twelve-people' => [
            ['Lucia Navarro', 'lucia.navarro@example.es', 'Your aunt is right. And never, ever add chorizo.', CommentStatus::Approved, 3],
            ['Pieter de Vries', 'pieter.devries@example.nl', 'Can I make this on a normal stove, or do I need a gas ring outside?', CommentStatus::Pending, 26],
        ],
    ];

    // Reply from the site team to the first comment on the Porto post.
    protected const REPLY = 'Thank you, Joana! The francesinha is on the list for our next trip.';

    public function run(): void
    {
        $team = User::query()->where('role', 'admin')->first();

        foreach (self::COMMENTS as $slug => $comments) {
            $entry = Entry::query()->ofType('post')->where('slug', $slug)->first();

            if ($entry === null || $entry->published_at === null || Comment::query()->where('entry_id', $entry->id)->exists()) {
                continue;
            }

            foreach ($comments as $index => [$name, $email, $text, $status, $hours]) {
                $comment = $this->comment($entry, [
                    'author_name' => $name,
                    'author_email' => $email,
                    'body' => $text,
                ], $status, $hours);

                if ($team !== null && $index === 0 && $slug === 'a-slow-weekend-in-porto') {
                    $this->comment($entry, [
                        'parent_id' => $comment->id,
                        'user_id' => $team->id,
                        'author_name' => $team->name,
                        'author_email' => $team->email,
                        'body' => self::REPLY,
                    ], CommentStatus::Approved, $hours + 2);
                }
            }
        }
    }

    /**
     * Saves one comment with its status and a date some hours after the post.
     *
     * @param  array<string, mixed>  $data
     */
    protected function comment(Entry $entry, array $data, CommentStatus $status, int $hours): Comment
    {
        $comment = new Comment(['entry_id' => $entry->id, ...$data]);
        $comment->status = $status;
        $comment->created_at = $entry->published_at?->copy()->addHours($hours);
        $comment->save();

        return $comment;
    }
}
