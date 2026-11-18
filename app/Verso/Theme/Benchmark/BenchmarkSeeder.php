<?php

namespace App\Verso\Theme\Benchmark;

use App\Models\User;
use App\Verso\Core\Enums\Role;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Fills an empty database with a realistic blog: posts with terms, a few pages and a primary menu.
class BenchmarkSeeder
{
    // Rows written per insert query.
    protected const CHUNK = 200;

    /** @var list<string> */
    protected const TOPICS = [
        'Slow travel through the Basque Country', 'Baking rye bread at home', 'Notes from a small vegetable garden',
        'Cycling the Danube in early spring', 'Repairing an old film camera', 'Cooking with seasonal mushrooms',
        'Learning to sail on the Baltic', 'A beginner guide to sourdough', 'Walking the Camino Portugués',
        'How we cut our energy bill', 'Reading list for rainy weekends', 'Building a bookshelf from oak',
    ];

    /** @var list<string> */
    protected const PARAGRAPHS = [
        'We left before sunrise, when the streets were still wet and the bakeries had just opened their doors.',
        'The first lesson was patience: nothing good happened when we tried to rush the dough or the weather.',
        'By noon the path turned toward the coast, and the smell of salt and pine followed us for hours.',
        'Most of the tools came from a flea market, and every one of them needed a little care before it worked.',
        'In the evening we wrote down what went well, what went wrong and what we would try next time.',
    ];

    /** @var array<string, string> */
    protected const CATEGORIES = ['travel' => 'Travel', 'food' => 'Food', 'craft' => 'Craft', 'home' => 'Home'];

    /** @var array<string, string> */
    protected const TAGS = ['spring' => 'Spring', 'budget' => 'Budget', 'family' => 'Family', 'outdoors' => 'Outdoors', 'weekend' => 'Weekend', 'tools' => 'Tools'];

    /**
     * Seeds the posts and returns the paths worth measuring.
     *
     * @return array<string, string> route name => path
     */
    public function seed(int $entries): array
    {
        $now = Carbon::now()->startOfMinute();
        $author = $this->author();
        $terms = $this->terms($now);
        $posts = $this->posts(max(1, $entries), $author, $now);
        $this->attachTerms($posts, $terms);
        $about = $this->pages($author, $now);
        $this->menu($about);

        return [
            'home' => '/',
            'entry' => '/blog/'.$this->slug(0),
            'archive' => '/blog',
            'term' => '/category/travel',
            'page' => '/about',
            'feed' => '/feed',
        ];
    }

    protected function author(): int
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Marta Ibáñez',
            'email' => 'marta@benchmark.verso.invalid',
            'password' => Hash::make(Str::random(32)),
            'role' => Role::Author,
            'email_verified_at' => Carbon::now(),
        ])->save();

        return $user->id;
    }

    /**
     * @return list<int> term ids
     */
    protected function terms(Carbon $now): array
    {
        $rows = [];

        foreach (['category' => self::CATEGORIES, 'tag' => self::TAGS] as $taxonomy => $names) {
            foreach ($names as $slug => $name) {
                $rows[] = ['taxonomy' => $taxonomy, 'name' => $name, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        DB::table('terms')->insert($rows);

        $ids = DB::table('terms')->orderBy('id')->pluck('id')->all();

        return array_values(array_map(intval(...), $ids));
    }

    /**
     * @return list<int> post ids, newest first
     */
    protected function posts(int $count, int $author, Carbon $now): array
    {
        foreach (array_chunk(range(0, $count - 1), self::CHUNK) as $chunk) {
            $rows = [];

            foreach ($chunk as $index) {
                $rows[] = $this->postRow($index, $author, $now);
            }

            DB::table('entries')->insert($rows);
        }

        $ids = DB::table('entries')->where('type', 'post')->orderByDesc('published_at')->pluck('id')->all();

        return array_values(array_map(intval(...), $ids));
    }

    /**
     * @return array<string, mixed>
     */
    protected function postRow(int $index, int $author, Carbon $now): array
    {
        $blocks = [['id' => 'b_h'.$index, 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'What we learned', 'children' => []]];

        foreach (self::PARAGRAPHS as $number => $text) {
            $blocks[] = ['id' => 'b_p'.$index.'_'.$number, 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>'.$text.' <strong>Day '.($number + 1).'.</strong></p>', 'children' => []];
        }

        $publishedAt = $now->copy()->subHours($index + 1);

        return [
            'type' => 'post',
            'title' => $this->title($index),
            'slug' => $this->slug($index),
            'status' => 'published',
            'blocks' => json_encode($blocks, JSON_THROW_ON_ERROR),
            'excerpt' => self::PARAGRAPHS[$index % count(self::PARAGRAPHS)],
            'author_id' => $author,
            'parent_id' => null,
            'menu_order' => 0,
            'published_at' => $publishedAt,
            'meta' => '{}',
            'created_at' => $publishedAt,
            'updated_at' => $publishedAt,
        ];
    }

    /**
     * Every post gets one category and two tags.
     *
     * @param  list<int>  $posts
     * @param  list<int>  $terms
     */
    protected function attachTerms(array $posts, array $terms): void
    {
        $categories = array_slice($terms, 0, count(self::CATEGORIES));
        $tags = array_slice($terms, count(self::CATEGORIES));
        $rows = [];

        foreach ($posts as $position => $post) {
            $rows[] = ['entry_id' => $post, 'term_id' => $categories[$position % count($categories)]];
            $rows[] = ['entry_id' => $post, 'term_id' => $tags[$position % count($tags)]];
            $rows[] = ['entry_id' => $post, 'term_id' => $tags[($position + 1) % count($tags)]];
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('entry_term')->insert($chunk);
        }
    }

    // An about page with a nested team page. Returns the about page id.
    protected function pages(int $author, Carbon $now): int
    {
        $page = fn (string $title, string $slug, ?int $parent): array => [
            'type' => 'page', 'title' => $title, 'slug' => $slug, 'status' => 'published',
            'blocks' => json_encode([['id' => 'b_'.$slug, 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>'.self::PARAGRAPHS[0].'</p>', 'children' => []]], JSON_THROW_ON_ERROR),
            'excerpt' => null, 'author_id' => $author, 'parent_id' => $parent, 'menu_order' => 0,
            'published_at' => $now, 'meta' => '{}', 'created_at' => $now, 'updated_at' => $now,
        ];

        $about = (int) DB::table('entries')->insertGetId($page('About us', 'about', null));
        DB::table('entries')->insert($page('Our team', 'team', $about));

        return $about;
    }

    protected function menu(int $about): void
    {
        $menu = new Menu(['name' => 'Main menu']);
        $menu->save();
        $menu->assignLocation('primary');

        $items = [
            ['type' => 'custom', 'label' => 'Blog', 'url' => '/blog', 'entry_id' => null],
            ['type' => 'entry', 'label' => null, 'url' => null, 'entry_id' => $about],
            ['type' => 'custom', 'label' => 'Travel', 'url' => '/category/travel', 'entry_id' => null],
        ];

        foreach ($items as $position => $item) {
            (new MenuItem([...$item, 'menu_id' => $menu->id, 'position' => $position]))->save();
        }
    }

    protected function title(int $index): string
    {
        return self::TOPICS[$index % count(self::TOPICS)].', part '.($index + 1);
    }

    protected function slug(int $index): string
    {
        return Str::slug($this->title($index));
    }
}
