<?php

use App\Models\User;
use App\Verso\Content\Actions\PublishEntry;
use App\Verso\Content\Jobs\PublishScheduledEntries;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Models\Redirect;
use App\Verso\Core\Settings\Settings;
use Database\Factories\EntryFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    config(['verso.page_cache.enabled' => false]);

    $this->author = User::factory()->author()->create(['name' => 'Lucía Ferreira']);
});

function publishedPost(array $attributes = []): Entry
{
    return Entry::factory()->published()->by(test()->author)->create($attributes);
}

function publishedPage(array $attributes = []): Entry
{
    return Entry::factory()->page()->published()->by(test()->author)->create($attributes);
}

describe('home', function () {
    it('lists the latest published posts', function () {
        publishedPost(['title' => 'A Slow Weekend in Porto', 'published_at' => now()->subDays(3)]);
        publishedPost(['title' => 'How We Bake Sourdough at Home', 'published_at' => now()->subDay()]);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['How We Bake Sourdough at Home', 'A Slow Weekend in Porto'])
            ->assertSee('Lucía Ferreira');
    });

    it('hides drafts, scheduled and trashed posts', function () {
        publishedPost(['title' => 'Cooking Paella for Twelve People']);
        Entry::factory()->by($this->author)->create(['title' => 'Draft About Winter Soups']);
        Entry::factory()->scheduled()->by($this->author)->create(['title' => 'Scheduled Spring Garden Plan']);
        Entry::factory()->trashed()->by($this->author)->create(['title' => 'Trashed Market Notes']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Cooking Paella for Twelve People')
            ->assertDontSee('Draft About Winter Soups')
            ->assertDontSee('Scheduled Spring Garden Plan')
            ->assertDontSee('Trashed Market Notes');
    });

    it('does not list pages', function () {
        publishedPage(['title' => 'About the Kitchen']);

        $this->get('/')->assertOk()->assertDontSee('<h2><a href="'.url('/about-the-kitchen').'">', false);
    });

    it('uses the posts per page setting', function () {
        app(Settings::class)->set('reading.posts_per_page', 2);

        foreach (['Choosing a Bike', 'Film Photography Basics', 'Solar Panels at Home'] as $index => $title) {
            publishedPost(['title' => $title, 'published_at' => now()->subDays($index + 1)]);
        }

        $this->get('/')->assertOk()
            ->assertSee('Choosing a Bike')
            ->assertSee('Film Photography Basics')
            ->assertDontSee('Solar Panels at Home')
            ->assertSee('Page 1 of 2');

        $this->get('/?page=2')->assertOk()
            ->assertSee('Solar Panels at Home')
            ->assertDontSee('Choosing a Bike');
    });

    it('returns 404 after the last page', function () {
        publishedPost();

        $this->get('/?page=7')->assertNotFound();
    });

    it('shows a friendly message when nothing is published', function () {
        $this->get('/')->assertOk()->assertSee('Nothing has been published here yet.');
    });

    it('shows the front page when one is set', function () {
        $front = publishedPage(['title' => 'Welcome to the Bakery', 'blocks' => EntryFactory::paragraphs('Fresh bread every morning.')]);
        publishedPost(['title' => 'Ten Lessons from a Year of Remote Work']);
        app(Settings::class)->set('reading.front_page', $front->id);

        $this->get('/')
            ->assertOk()
            ->assertSee('Welcome to the Bakery')
            ->assertSee('Fresh bread every morning.')
            ->assertDontSee('Ten Lessons from a Year of Remote Work');
    });

    it('ignores a front page that is not published', function () {
        $front = Entry::factory()->page()->by($this->author)->create(['title' => 'Unfinished Welcome Page']);
        publishedPost(['title' => 'Reading List for Long Winter Nights']);
        app(Settings::class)->set('reading.front_page', $front->id);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Unfinished Welcome Page')
            ->assertSee('Reading List for Long Winter Nights');
    });

    it('sends the front page own path to the home page', function () {
        $front = publishedPage(['title' => 'Welcome', 'slug' => 'welcome']);
        app(Settings::class)->set('reading.front_page', $front->id);

        $this->get('/welcome')->assertStatus(301)->assertRedirect('/');
    });
});

describe('blog', function () {
    it('lists posts on the archive page', function () {
        publishedPost(['title' => 'Notes from a Rainy Hike in the Pyrenees']);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('<h1 class="page-title">Posts</h1>', false)
            ->assertSee('Notes from a Rainy Hike in the Pyrenees');
    });

    it('shows a published post with its rendered blocks', function () {
        $entry = publishedPost([
            'title' => 'The Best Coffee Shops in Lisbon',
            'slug' => 'coffee-lisbon',
            'excerpt' => 'Six places for a slow morning.',
            'blocks' => [
                ['id' => 'b_intro1', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Alfama mornings', 'children' => []],
                ['id' => 'b_intro2', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Start at <strong>Copenhagen Coffee Lab</strong>.<script>alert(1)</script>', 'children' => []],
            ],
        ]);

        $this->get('/blog/coffee-lisbon')
            ->assertOk()
            ->assertSee('<title>The Best Coffee Shops in Lisbon'.config('verso.seo.title_separator').e(app(Settings::class)->get('site.name')).'</title>', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('<meta name="description" content="Six places for a slow morning.">', false)
            ->assertSee('<link rel="canonical" href="'.$entry->url().'">', false)
            ->assertSee('<h2>Alfama mornings</h2>', false)
            ->assertSee('<strong>Copenhagen Coffee Lab</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    });

    it('shows categories and tags of a post', function () {
        $entry = publishedPost(['slug' => 'harvest-day']);
        $category = Term::factory()->create(['name' => 'Gardening', 'slug' => 'gardening']);
        $tag = Term::factory()->tag()->create(['name' => 'Tomatoes', 'slug' => 'tomatoes']);
        $entry->terms()->attach([$category->id, $tag->id]);

        $this->get('/blog/harvest-day')
            ->assertOk()
            ->assertSee('href="'.url('/category/gardening').'"', false)
            ->assertSee('#Tomatoes');
    });

    it('returns 404 for drafts', function () {
        Entry::factory()->by($this->author)->create(['slug' => 'secret-recipe']);

        $this->get('/blog/secret-recipe')->assertNotFound();
    });

    it('returns 404 for scheduled posts until they are published', function () {
        $entry = Entry::factory()->by($this->author)->create(['slug' => 'spring-menu']);
        $entry->schedule(now()->addHour());

        $this->get('/blog/spring-menu')->assertNotFound();

        Carbon::setTestNow(now()->addHours(2));
        $this->get('/blog/spring-menu')->assertNotFound();

        (new PublishScheduledEntries)->handle(app(PublishEntry::class));

        $this->get('/blog/spring-menu')->assertOk();
    });

    it('returns 404 for posts published in the future', function () {
        publishedPost(['slug' => 'future-harvest', 'published_at' => now()->addDay()]);

        $this->get('/blog/future-harvest')->assertNotFound();
    });

    it('returns 404 for trashed posts', function () {
        publishedPost(['slug' => 'old-market-notes'])->moveToTrash();

        $this->get('/blog/old-market-notes')->assertNotFound();
    });

    it('does not show pages under the blog', function () {
        publishedPage(['slug' => 'about']);

        $this->get('/blog/about')->assertNotFound();
    });

    it('does not show posts through the page catch-all', function () {
        publishedPost(['slug' => 'bike-commuting']);

        $this->get('/bike-commuting')->assertNotFound();
    });
});

describe('pages', function () {
    it('shows a top level page', function () {
        publishedPage(['title' => 'About Us', 'slug' => 'about', 'blocks' => EntryFactory::paragraphs('We are a small team in Valencia.')]);

        $this->get('/about')->assertOk()->assertSee('About Us')->assertSee('We are a small team in Valencia.');
    });

    it('shows nested pages with breadcrumbs', function () {
        $about = publishedPage(['title' => 'About Us', 'slug' => 'about']);
        $team = publishedPage(['title' => 'Our Team', 'slug' => 'team', 'parent_id' => $about->id]);
        publishedPage(['title' => 'Marta Ruiz', 'slug' => 'marta', 'parent_id' => $team->id]);

        $this->get('/about/team/marta')
            ->assertOk()
            ->assertSee('Marta Ruiz')
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSeeInOrder(['About Us', 'Our Team', 'Marta Ruiz']);
    });

    it('lists published child pages', function () {
        $about = publishedPage(['title' => 'About Us', 'slug' => 'about']);
        publishedPage(['title' => 'Our Team', 'slug' => 'team', 'parent_id' => $about->id]);
        Entry::factory()->page()->by($this->author)->create(['title' => 'Hidden Careers Page', 'slug' => 'careers', 'parent_id' => $about->id]);

        $this->get('/about')->assertOk()->assertSee('In this section')->assertSee('Our Team')->assertDontSee('Hidden Careers Page');
    });

    it('returns 404 when the parent path is wrong', function () {
        $about = publishedPage(['slug' => 'about']);
        publishedPage(['slug' => 'team', 'parent_id' => $about->id]);
        publishedPage(['slug' => 'contact']);

        $this->get('/contact/team')->assertNotFound();
        $this->get('/team')->assertNotFound();
    });

    it('returns 404 when a parent is not published', function () {
        $about = Entry::factory()->page()->by($this->author)->create(['slug' => 'about']);
        publishedPage(['slug' => 'team', 'parent_id' => $about->id]);

        $this->get('/about/team')->assertNotFound();
    });

    it('lists top level pages in the navigation', function () {
        publishedPage(['title' => 'Contact', 'slug' => 'contact', 'menu_order' => 2]);
        publishedPage(['title' => 'About Us', 'slug' => 'about', 'menu_order' => 1]);

        $this->get('/')->assertSeeInOrder(['aria-label="Main"', 'About Us', 'Contact'], false);
    });
});

describe('terms', function () {
    it('lists the posts of a category', function () {
        $travel = Term::factory()->create(['name' => 'Travel', 'slug' => 'travel', 'description' => 'Trips around Iberia.']);
        publishedPost(['title' => 'A Slow Weekend in Porto'])->terms()->attach($travel);
        publishedPost(['title' => 'Why Our Garden Loves Tomatoes']);
        Entry::factory()->by($this->author)->create(['title' => 'Draft Trip to Seville'])->terms()->attach($travel);

        $this->get('/category/travel')
            ->assertOk()
            ->assertSee('Trips around Iberia.')
            ->assertSee('A Slow Weekend in Porto')
            ->assertDontSee('Why Our Garden Loves Tomatoes')
            ->assertDontSee('Draft Trip to Seville');
    });

    it('lists the posts of a tag', function () {
        $tag = Term::factory()->tag()->create(['name' => 'Coffee', 'slug' => 'coffee']);
        publishedPost(['title' => 'The Best Coffee Shops in Lisbon'])->terms()->attach($tag);

        $this->get('/tag/coffee')->assertOk()->assertSee('Tag')->assertSee('The Best Coffee Shops in Lisbon');
    });

    it('returns 404 for unknown terms', function () {
        $this->get('/category/astronomy')->assertNotFound();
    });

    it('does not mix taxonomies', function () {
        Term::factory()->tag()->create(['slug' => 'coffee']);

        $this->get('/category/coffee')->assertNotFound();
    });
});

describe('search', function () {
    it('finds published entries by title and excerpt', function () {
        publishedPost(['title' => 'How We Bake Sourdough at Home', 'excerpt' => 'Flour, water and patience.']);
        publishedPost(['title' => 'Our First Month with Solar Panels', 'excerpt' => 'The bread oven now runs on sun.']);
        publishedPost(['title' => 'Choosing a Bike for City Commuting', 'excerpt' => 'Steel or aluminium?']);
        Entry::factory()->by($this->author)->create(['title' => 'Draft Bread Recipes']);

        $this->get('/search?q=bread')
            ->assertOk()
            ->assertSee('Results for “bread”', false)
            ->assertSee('One entry found.')
            ->assertSee('Our First Month with Solar Panels')
            ->assertDontSee('Choosing a Bike for City Commuting')
            ->assertDontSee('Draft Bread Recipes');
    });

    it('shows a count for several results', function () {
        publishedPost(['title' => 'Bread in Porto']);
        publishedPost(['title' => 'Bread in Lisbon']);

        $this->get('/search?q=bread')->assertSee('2 entries found.');
    });

    it('says when nothing matches', function () {
        publishedPost(['title' => 'Cooking Paella for Twelve People']);

        $this->get('/search?q=astronomy')->assertOk()->assertSee('No entries found.');
    });

    it('treats like wildcards as plain text', function () {
        publishedPost(['title' => 'Cooking Paella for Twelve People']);

        $this->get('/search?q=%25%25')->assertOk()->assertSee('No entries found.');
    });

    it('shows only the form for very short searches', function () {
        publishedPost(['title' => 'A Beginner Guide to Film Photography']);

        $this->get('/search?q=a')->assertOk()->assertSee('<h1 class="page-title">', false)->assertDontSee('entries found');
        $this->get('/search')->assertOk()->assertSee('name="q"', false);
    });

    it('asks robots not to index results', function () {
        $this->get('/search?q=garden')->assertSee('<meta name="robots" content="noindex">', false);
    });
});

describe('not found and routing', function () {
    it('renders the theme 404 page', function () {
        $this->get('/a-page-that-never-existed')
            ->assertNotFound()
            ->assertSee('This page could not be found')
            ->assertSee('<meta name="robots" content="noindex">', false);
    });

    it('applies stored redirects before showing a 404', function () {
        publishedPage(['slug' => 'contact']);
        Redirect::point('/get-in-touch', '/contact');

        $this->get('/get-in-touch')->assertStatus(301)->assertRedirect('/contact');
    });

    it('adds the shared middleware to every public route', function () {
        foreach (['home', 'theme.search', 'theme.feed', 'theme.page'] as $name) {
            expect(Route::getRoutes()->getByName($name)->gatherMiddleware())
                ->toContain('verso.redirects', 'verso.theme', 'verso.cache');
        }
    });

    it('keeps the page catch-all as the last route', function () {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => in_array('GET', $route->methods()));

        expect($routes->last()->getName())->toBe('theme.page')
            ->and($routes->last()->isFallback)->toBeTrue();
    });

    it('does not hide routes from other modules', function () {
        Route::middleware('web')->get('/opening-hours', fn () => 'Open from nine to five');

        $this->get('/opening-hours')->assertOk()->assertSee('Open from nine to five');
    });

    it('keeps the account settings pages working', function () {
        $this->actingAs(User::factory()->create())->get('/settings/profile')->assertOk();
    });
});
