<?php

namespace App\Verso\Content;

use App\Models\User;
use App\Verso\Access\Permissions\EntryPermissions;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\Admin\RecentEntriesPanel;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Jobs\PublishScheduledEntries;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Support\SlugGenerator;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardPanel;
use App\Verso\Core\Admin\DashboardStat;
use App\Verso\Core\Routing\AdminRoutes;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Boots the content module: content types, taxonomies, admin pages and the publish job.
class ContentServiceProvider extends ServiceProvider
{
    protected const OTHER_TYPES = '*';

    protected const PUBLISHED_STAT_LABELS = [
        'post' => ['label' => 'Published posts'],
        'page' => ['label' => 'Published pages'],
        self::OTHER_TYPES => ['label' => 'Published :types'],
    ];

    public function register(): void
    {
        $this->app->singleton(ContentTypeRegistry::class);
        $this->app->singleton(TaxonomyRegistry::class);
        $this->app->singleton(SlugGenerator::class, fn (): SlugGenerator => new SlugGenerator(
            maxLength: (int) config('verso.content.slug_max_length'),
            reserved: array_values(array_map(strval(...), (array) config('verso.content.reserved_slugs'))),
        ));
    }

    public function boot(): void
    {
        $this->registerBuiltInTypes();
        $this->registerRouteBindings();

        AdminRoutes::load(base_path('routes/verso/content.php'));

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new PublishScheduledEntries)
                ->cron((string) config('verso.content.publish_schedule'))
                ->name('verso:publish-scheduled-entries')
                ->withoutOverlapping();
        });

        // Wait until plugins have registered their types too.
        $this->app->booted(function (): void {
            $this->app->make(ContentAdminNav::class)->register();
            $this->registerDashboardStats();
        });
    }

    protected function registerBuiltInTypes(): void
    {
        $types = $this->app->make(ContentTypeRegistry::class);
        $types->register(new ContentType(
            name: 'post', label: 'Post', pluralLabel: 'Posts', hierarchical: false, hasArchive: true,
            routeBase: 'blog', icon: 'posts', menuOrder: 10, taxonomies: ['category', 'tag'],
        ));
        $types->register(new ContentType(
            name: 'page', label: 'Page', pluralLabel: 'Pages', hierarchical: true, hasArchive: false,
            routeBase: '', icon: 'pages', menuOrder: 20,
        ));

        $taxonomies = $this->app->make(TaxonomyRegistry::class);
        $taxonomies->register(new Taxonomy(
            name: 'category', label: 'Category', pluralLabel: 'Categories', hierarchical: true,
            contentTypes: ['post'], routeBase: 'category', icon: 'categories', menuOrder: 10,
        ));
        $taxonomies->register(new Taxonomy(
            name: 'tag', label: 'Tag', pluralLabel: 'Tags', hierarchical: false,
            contentTypes: ['post'], routeBase: 'tag', icon: 'tags', menuOrder: 20,
        ));
    }

    // Admin URLs use names ("post", "category"). Unknown names give a 404.
    protected function registerRouteBindings(): void
    {
        Route::bind('contentType', fn (string $value): ContentType => $this->app->make(ContentTypeRegistry::class)->find($value) ?? abort(404));
        Route::bind('taxonomy', fn (string $value): Taxonomy => $this->app->make(TaxonomyRegistry::class)->find($value) ?? abort(404));
    }

    protected function registerDashboardStats(): void
    {
        $dashboard = $this->app->make(Dashboard::class);

        foreach ($this->app->make(ContentTypeRegistry::class)->all() as $type) {
            $dashboard->addStat(new DashboardStat(
                key: 'published.'.$type->name,
                label: $this->publishedStatLabel($type),
                count: fn (): int => Entry::query()->ofType($type->name)->published()->count(),
                order: $type->menuOrder,
                capability: fn (User $user): bool => $user->can('viewAny', [Entry::class, $type->name]),
                route: 'admin.entries.index',
                parameters: ['contentType' => $type->name, 'status' => EntryStatus::Published->value],
                icon: $type->icon,
                labelReplace: ['types' => fn (): string => $this->inlinePluralLabel($type)],
            ));
        }

        $dashboard->addStat(new DashboardStat(
            key: 'drafts',
            label: 'Drafts',
            count: fn (): int => Entry::query()->where('status', EntryStatus::Draft)->count(),
            order: 50,
            capability: fn (User $user): bool => $user->can('viewAny', Entry::class),
            icon: 'entries',
        ));

        $dashboard->addPanel(new DashboardPanel(
            key: 'recent-entries',
            label: 'Recent entries',
            items: (new RecentEntriesPanel($this->app->make(ContentTypeRegistry::class), (int) config('verso.admin.dashboard_items'), $this->app->make(EntryPermissions::class)))(...),
            icon: 'entries',
            emptyText: 'No entries yet. Write your first post to see it here.',
            order: 10,
            capability: fn (User $user): bool => $user->can('viewAny', Entry::class),
        ));

        $dashboard->addStat(new DashboardStat(
            key: 'scheduled',
            label: 'Scheduled',
            count: fn (): int => Entry::query()->where('status', EntryStatus::Scheduled)->count(),
            order: 60,
            capability: fn (User $user): bool => $user->can('viewAny', Entry::class),
            icon: 'calendar',
        ));
    }

    protected function publishedStatLabel(ContentType $type): string
    {
        return (self::PUBLISHED_STAT_LABELS[$type->name] ?? self::PUBLISHED_STAT_LABELS[self::OTHER_TYPES])['label'];
    }

    protected function inlinePluralLabel(ContentType $type): string
    {
        $labels = $this->app->make(ContentLabels::class);

        return $labels->inline($labels->typePluralLabel($type));
    }
}
