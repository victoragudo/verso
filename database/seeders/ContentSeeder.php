<?php

namespace Database\Seeders;

use App\Models\User;
use App\Verso\Content\Actions\CreateEntry;
use App\Verso\Content\Actions\CreateTerm;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Enums\Role;
use App\Verso\Media\Models\Media;
use Database\Seeders\Demo\DemoAuthors;
use Database\Seeders\Demo\DemoBlocks;
use Database\Seeders\Demo\DemoPosts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// Seeds the demo writers, posts, pages, categories and tags. Running it again adds nothing new.
class ContentSeeder extends Seeder
{
    /** @var array<string, string> */
    protected const CATEGORIES = [
        'Travel' => 'City guides, slow trips and notes from the road.',
        'Food' => 'Recipes and kitchen stories from a small home kitchen.',
        'Garden' => 'Growing vegetables and flowers on a city balcony.',
        'Work' => 'Remote work, tools and habits that last.',
        'Reading' => 'Books and essays we keep coming back to.',
    ];

    /** @var list<string> */
    protected const TAGS = ['Portugal', 'Spain', 'Recipes', 'Bread', 'Cycling', 'Photography', 'Solar', 'Books', 'Lisbon', 'Tools'];

    /**
     * Title, excerpt, blocks (as DemoBlocks lists) and page template. The second page is a child of the first.
     *
     * @var list<array{0: string, 1: string, 2: list<list<mixed>>}>
     */
    protected const PAGES = [
        ['About', 'A small journal about travel, food and simple living, written by three friends.', [
            ['p', 'Verso Journal is a small blog about travel, food and simple living. It is written by three friends who like long walks, good bread and honest tools.'],
            ['h2', 'What we write about'],
            ['ul', ['<strong>Travel</strong>: slow trips by train, on foot and by bike', '<strong>Food</strong>: recipes we have cooked at least ten times', '<strong>Garden</strong>: a balcony that thinks it is a farm', '<strong>Work and reading</strong>: tools, habits and books that last']],
            ['quote', 'We publish when we have something worth saying, and not a day before.', 'The three of us'],
            ['p', 'We never take money for reviews and we never sell your data. If a post helped you, the best thanks is to share it with a friend.'],
        ]],
        ['Our Team', 'The three people behind the journal.', [
            ['columns', [['Clara Montes', 'Writes about food and gardens and tests every recipe twice.'], ['Pablo Ortega', 'Takes the photos and plans the trips, usually by train.']]],
            ['p', 'Ana Ribeiro keeps everything running, from the newsletter to the server.'],
        ]],
        ['Contact', 'Questions, ideas or a good recipe to share? Write to us.', [
            ['p', 'We read every message and answer within a few days. For quick questions you can also write to hello@verso.test.'],
        ]],
        ['Privacy Policy', 'What we store and why.', [
            ['p', 'We only store the data needed to run this site. We do not sell or share your data.'],
            ['p', 'Comments keep your name and the text you wrote. Your email address is never shown.'],
        ]],
    ];

    public function run(): void
    {
        $admin = User::query()->where('role', Role::Admin->value)->orderBy('id')->firstOrFail();
        $media = Media::query()->whereIn('path', array_map(MediaSeeder::path(...), [...array_keys(MediaSeeder::IMAGES), ...array_keys(MediaSeeder::AVATARS)]))->get()->keyBy(fn (Media $item): string => pathinfo($item->filename, PATHINFO_FILENAME));
        $authors = $this->authors($media) + ['admin' => $admin];
        $blocks = new DemoBlocks($media);

        $categories = $this->terms('category', self::CATEGORIES);
        $tags = $this->terms('tag', array_fill_keys(self::TAGS, null));

        foreach (DemoPosts::all() as $post) {
            $this->post($post, $authors[$post['author']] ?? $admin, $blocks, $media, $categories, $tags);
        }

        $this->pages($admin, $blocks);
    }

    /**
     * The demo writers, with their bio and portrait. Existing users with the same email are kept.
     *
     * @param  Collection<string, Media>  $media
     * @return array<string, User>
     */
    protected function authors(Collection $media): array
    {
        $users = [];

        foreach (DemoAuthors::AUTHORS as $key => $author) {
            $user = User::query()->firstOrNew(['email' => $author['email']]);

            if (! $user->exists) {
                $user->fill(['name' => $author['name'], 'password' => Str::password(32)]);
                $user->role = Role::Author;
            }

            $user->bio ??= $author['bio'];
            $user->avatar_media_id ??= $media->get($author['avatar'])?->id;
            $user->save();

            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * Terms by name, created when missing.
     *
     * @param  array<string, string|null>  $names  name => description
     * @return array<string, Term>
     */
    protected function terms(string $taxonomy, array $names): array
    {
        $createTerm = app(CreateTerm::class);
        $existing = Term::query()->inTaxonomy($taxonomy)->get()->keyBy('name');
        $terms = [];

        foreach ($names as $name => $description) {
            $terms[$name] = $existing->get($name) ?? $createTerm->handle($taxonomy, ['name' => $name, 'description' => $description]);
        }

        return $terms;
    }

    /**
     * @param  array{title: string, category: string, tags: list<string>, author: string, days: int|null, image: string|null, excerpt: string, blocks: list<list<mixed>>}  $post
     * @param  Collection<string, Media>  $media
     * @param  array<string, Term>  $categories
     * @param  array<string, Term>  $tags
     */
    protected function post(array $post, User $author, DemoBlocks $blocks, Collection $media, array $categories, array $tags): void
    {
        if (Entry::query()->ofType('post')->where('slug', Str::slug($post['title']))->exists()) {
            return;
        }

        $image = $post['image'] === null ? null : $media->get($post['image']);

        app(CreateEntry::class)->handle($author, 'post', [
            'title' => $post['title'],
            'excerpt' => $post['excerpt'],
            'blocks' => $blocks->build($post['blocks']),
            'meta' => $image === null ? [] : [
                'featured_image' => ['mediaId' => $image->id, 'src' => $image->url(), 'alt' => $image->alt],
            ],
            'status' => $this->statusFor($post['days']),
            'published_at' => $this->dateFor($post['days']),
            'terms' => [
                'category' => [$categories[$post['category']]->id],
                'tag' => array_map(fn (string $tag): int => $tags[$tag]->id, $post['tags']),
            ],
        ]);
    }

    protected function pages(User $admin, DemoBlocks $blocks): void
    {
        $parentId = null;

        foreach (self::PAGES as $order => [$title, $excerpt, $content]) {
            $page = Entry::query()->ofType('page')->where('slug', Str::slug($title))->first() ?? app(CreateEntry::class)->handle($admin, 'page', [
                'title' => $title,
                'excerpt' => $excerpt,
                'blocks' => $blocks->build($content),
                'status' => 'published',
                'menu_order' => $order,
                'parent_id' => $order === 1 ? $parentId : null,
            ]);
            $parentId ??= $page->id;
        }
    }

    // A draft has no date, a negative number of days is in the future.
    protected function statusFor(?int $daysAgo): string
    {
        return match (true) {
            $daysAgo === null => 'draft',
            $daysAgo < 0 => 'scheduled',
            default => 'published',
        };
    }

    protected function dateFor(?int $daysAgo): ?string
    {
        return $daysAgo === null ? null : now()->subDays($daysAgo)->setTime(9, 30)->toDateTimeString();
    }
}
