<?php

use App\Models\User;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Settings\Settings;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\PluginManager;
use App\Verso\Site\Models\Menu;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DemoThemeSeeder;
use Database\Seeders\MediaSeeder;
use Database\Seeders\PluginSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);
});

it('names the demo site and turns on the demo theme', function () {
    expect(app(Settings::class)->get('site.name'))->toBe('Verso Journal')
        ->and(app(Settings::class)->get('theme.active'))->toBe('ashgrove');
});

it('keeps the default theme when the demo theme is not installed', function () {
    app(Settings::class)->set('theme.active', 'default');
    config(['verso.demo.theme' => 'paper-lantern']);

    $this->seed(DemoThemeSeeder::class);

    expect(app(Settings::class)->get('theme.active'))->toBe('default');
});

it('creates three writers with a bio and a portrait', function () {
    $writers = User::query()->where('role', Role::Author->value)->with('avatar')->orderBy('name')->get();

    expect($writers->pluck('name')->all())->toBe(['Ana Ribeiro', 'Clara Montes', 'Pablo Ortega'])
        ->and($writers->every(fn (User $writer) => $writer->bio !== null && $writer->avatar?->width === 320))->toBeTrue()
        ->and(Entry::firstWhere('slug', 'a-slow-weekend-in-porto')->author->name)->toBe('Pablo Ortega');
});

it('adds nothing new when the demo content is seeded again', function () {
    $counts = fn () => [Entry::count(), Term::count(), Media::count(), User::count(), Comment::count(), Menu::count()];
    $before = $counts();

    $this->seed(DemoContentSeeder::class);

    expect($counts())->toBe($before)
        ->and(Storage::disk('public')->files('demo'))->toHaveCount(18);
});

it('creates the admin user', function () {
    $admin = User::firstWhere('email', 'admin@verso.test');

    expect($admin->role)->toBe(Role::Admin)
        ->and(Hash::check('password', $admin->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull();
});

it('creates fourteen posts and four pages', function () {
    expect(Entry::ofType('post')->count())->toBe(14)
        ->and(Entry::ofType('page')->count())->toBe(4)
        ->and(Entry::ofType('post')->published()->count())->toBe(12)
        ->and(Entry::where('status', EntryStatus::Scheduled)->count())->toBe(1)
        ->and(Entry::where('status', EntryStatus::Draft)->count())->toBe(1);
});

it('creates categories and tags and links them to posts', function () {
    expect(Term::inTaxonomy('category')->count())->toBe(5)
        ->and(Term::inTaxonomy('tag')->count())->toBe(10)
        ->and(Entry::firstWhere('slug', 'a-slow-weekend-in-porto')->terms->pluck('name')->sort()->values()->all())
        ->toBe(['Photography', 'Portugal', 'Travel']);
});

it('nests the team page under about', function () {
    expect(Entry::firstWhere('slug', 'our-team')->path())->toBe('/about/our-team');
});

it('gives every post real paragraph blocks and uses many block types', function () {
    $allTypes = [];

    Entry::ofType('post')->each(function (Entry $entry) use (&$allTypes) {
        $types = array_column($entry->blocks, 'type');
        $allTypes = [...$allTypes, ...$types];

        expect($entry->blocks)->not->toBeEmpty()
            ->and($types)->toContain('paragraph');
    });

    expect(array_unique($allTypes))->toContain('heading', 'list', 'quote', 'code', 'table', 'gallery', 'embed', 'columns', 'button', 'separator');
});

it('stores real JPEG photos and author portraits on the public disk with alt text', function () {
    $media = Media::all();

    expect($media)->toHaveCount(18)
        ->and($media->pluck('alt')->filter()->count())->toBe(18)
        ->and($media->firstWhere('filename', 'porto-ribeira-at-dusk.jpg')->alt)
        ->toBe('Colourful houses of the Ribeira district in Porto above the Douro river at dusk.');

    $media->each(function (Media $item) {
        $bytes = Storage::disk('public')->get($item->path);
        $size = getimagesizefromstring($bytes);

        expect($item->disk)->toBe('public')
            ->and($item->path)->toStartWith('demo/')
            ->and($item->mime_type)->toBe('image/jpeg')
            ->and($item->size)->toBe(strlen($bytes))
            ->and($size['mime'])->toBe('image/jpeg')
            ->and([$size[0], $size[1]])->toBe([$item->width, $item->height])
            ->and($item->uploaded_by)->toBe(User::firstWhere('email', 'admin@verso.test')->id);
    });
});

it('uses the configured size for the demo photos', function () {
    config(['verso.demo.image_width' => 320, 'verso.demo.image_height' => 200]);
    Media::query()->delete();

    $this->seed(MediaSeeder::class);

    $size = getimagesizefromstring(Storage::disk('public')->get(Media::first()->path));

    expect([$size[0], $size[1]])->toBe([320, 200]);
});

it('writes the same files again when it seeds a second time', function () {
    Media::query()->delete();

    $this->seed(MediaSeeder::class);

    expect(Storage::disk('public')->files('demo'))->toHaveCount(18)
        ->and(Media::count())->toBe(18);
});

it('adds featured images to posts and a gallery to the Porto post', function () {
    $porto = Entry::firstWhere('slug', 'a-slow-weekend-in-porto');
    $photo = Media::firstWhere('filename', 'porto-ribeira-at-dusk.jpg');
    $gallery = collect($porto->blocks)->firstWhere('type', 'gallery');

    expect($porto->meta['featured_image'])->toMatchArray(['mediaId' => $photo->id, 'alt' => $photo->alt])
        ->and($porto->blocks[0]['type'])->toBe('paragraph')
        ->and($gallery['attrs']['mediaIds'])->toHaveCount(3)
        ->and($gallery['attrs']['images'][0])->toMatchArray(['alt' => 'Blue and white azulejo tiles on the front of an old house in Porto.', 'width' => 960])
        ->and(Entry::ofType('post')->get()->filter(fn (Entry $entry) => isset($entry->meta['featured_image']))->count())->toBe(12);
});

it('shows the photos and menus on the public site', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('/category/travel"', false)
        ->assertSee('/privacy-policy">Privacy</a>', false)
        ->assertSee('RSS feed')
        ->assertSee('class="verso-featured-image post-card-image"', false)
        ->assertSee('alt="A round sourdough loaf', false);

    $this->get(Entry::firstWhere('slug', 'a-slow-weekend-in-porto')->path())
        ->assertOk()
        ->assertSee('alt="Colourful houses of the Ribeira district', false)
        ->assertSee('/img/demo/porto-ribeira-at-dusk.jpg', false);
});

it('shows each demo photo once on its post page', function () {
    $html = $this->get(Entry::firstWhere('slug', 'a-slow-weekend-in-porto')->path())->getContent();

    expect(substr_count($html, 'alt="Colourful houses of the Ribeira district'))->toBe(1);
});

it('creates the primary and footer menus', function () {
    $primary = Menu::forLocation('primary');
    $footer = Menu::forLocation('footer');

    expect($primary->name)->toBe('Main navigation')
        ->and($primary->items()->whereNull('parent_id')->count())->toBe(4)
        ->and($primary->items()->whereNotNull('parent_id')->count())->toBe(6)
        ->and($footer->name)->toBe('Footer links')
        ->and($footer->items()->count())->toBe(4);
});

it('creates approved and pending comments with a reply from the team', function () {
    $porto = Entry::firstWhere('slug', 'a-slow-weekend-in-porto');
    $reply = Comment::whereNotNull('parent_id')->first();

    expect(Comment::withStatus(CommentStatus::Approved)->count())->toBe(8)
        ->and(Comment::withStatus(CommentStatus::Pending)->count())->toBe(3)
        ->and($reply->parent->author_name)->toBe('Joana Ribeiro')
        ->and($reply->user_id)->toBe(User::firstWhere('email', 'admin@verso.test')->id)
        ->and(Comment::where('entry_id', $porto->id)->count())->toBe(4);

    Comment::with('entry')->get()->each(fn (Comment $comment) => expect($comment->created_at->greaterThan($comment->entry->published_at))->toBeTrue());
});

it('shows only approved comments on the post', function () {
    $this->get(Entry::firstWhere('slug', 'a-slow-weekend-in-porto')->path())
        ->assertSee('Lovely post! Next time try the francesinha')
        ->assertDontSee('Which cellar in Gaia would you recommend');
});

it('enables the Reading Time and Forms plugins for the demo', function () {
    expect(app(Settings::class)->get('plugins.enabled'))->toBe(['reading-time', 'forms'])
        ->and(app(PluginManager::class)->find('reading-time')->status())->toBe('enabled')
        ->and(app(PluginManager::class)->find('forms')->status())->toBe('enabled');
});

it('puts the demo contact form on the Contact page', function () {
    $page = Entry::firstWhere('slug', 'contact');

    expect(array_column($page->blocks, 'type'))->toBe(['paragraph', 'form']);

    $this->get($page->path())
        ->assertOk()
        ->assertSee('Send us a message')
        ->assertSee('id="form-contact"', false);
});

it('enables the demo plugins from the config', function () {
    app(Settings::class)->set('plugins.enabled', []);
    config(['verso.demo.plugins' => []]);

    $this->seed(PluginSeeder::class);

    expect(app(Settings::class)->get('plugins.enabled'))->toBe([]);
});

it('skips demo plugins that are missing', function () {
    app(Settings::class)->set('plugins.enabled', []);
    config(['verso.demo.plugins' => ['photo-carousel', 'reading-time']]);

    $this->seed(PluginSeeder::class);

    expect(app(Settings::class)->get('plugins.enabled'))->toBe(['reading-time']);
});

it('boots the Reading Time plugin so its block is ready in the editor', function () {
    expect(app(PluginManager::class)->registerEnabled())->toBe(['reading-time', 'forms'])
        ->and(app(PluginBlockRegistry::class)->has('reading-time'))->toBeTrue();
});
