<?php

namespace App\Verso\Comments;

use App\Verso\Comments\Actions\SubmitComment;
use App\Verso\Comments\Admin\PendingCommentsPanel;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\Comments\Support\CommentAvailability;
use App\Verso\Comments\Support\FormTimer;
use App\Verso\Comments\Support\SpamScore;
use App\Verso\Comments\View\CommentsComponent;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardPanel;
use App\Verso\Core\Admin\DashboardStat;
use App\Verso\Core\Admin\ListColumn;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Core\Settings\Settings;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Filters\AdminEntryColumnsFilter;
use App\Verso\Plugins\Filters\AdminEntryRowFilter;
use App\Verso\Plugins\Slots\AdminSlot;
use App\Verso\Plugins\Slots\AdminSlotRegistry;
use App\Verso\Plugins\Slots\SlotItem;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Boots the comments module: public endpoint, moderation page and the Blade component.
class CommentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SpamScore::class, fn (): SpamScore => new SpamScore((int) config('verso.comments.spam_links')));
        $this->app->singleton(FormTimer::class, fn (): FormTimer => new FormTimer(
            minSeconds: (int) config('verso.comments.min_seconds'),
            maxSeconds: (int) config('verso.comments.max_seconds'),
        ));
        $this->app->bind(CommentAvailability::class, fn (Application $app): CommentAvailability => new CommentAvailability(
            settings: $app->make(Settings::class),
            openByDefault: (bool) config('verso.comments.open_by_default'),
        ));
        $this->app->bind(SubmitComment::class, fn (Application $app): SubmitComment => new SubmitComment(
            availability: $app->make(CommentAvailability::class),
            spamScore: $app->make(SpamScore::class),
            maxDepth: (int) config('verso.comments.max_depth'),
            ipSecret: (string) config('app.key'),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/verso/comments'), 'verso-comments');
        Blade::component('verso-comments', CommentsComponent::class);

        Entry::resolveRelationUsing('comments', fn (Entry $entry): HasMany => $entry->hasMany(Comment::class));
        $this->registerListColumn();

        RateLimiter::for('verso-comments', fn (Request $request): Limit => Limit::perMinutes(
            max((int) config('verso.comments.throttle_minutes'), 1),
            max((int) config('verso.comments.throttle_attempts'), 1),
        )->by((string) $request->ip()));

        AdminRoutes::load(base_path('routes/verso/comments.php'));

        // The "Allow comments" switch in the Publish card of the entry editor.
        $this->app->make(AdminSlotRegistry::class)->add(new SlotItem(
            slot: AdminSlot::EntryPublish,
            key: 'comments',
            component: 'comments.switch',
            order: 10,
            props: fn (Request $request): array => ['open' => $this->commentsOpen($request->route('entry'))],
        ));
        Route::group([], base_path('routes/verso/comments-public.php'));

        $this->app->booted(function (): void {
            AdminNav::add(new NavItem(key: 'comments', label: 'Comments', route: 'admin.comments.index', icon: 'comments', order: 40, capability: Capability::ModerateComments, keywords: ['moderation', 'replies', 'spam', 'comentarios']));
        });

        $this->app->make(Dashboard::class)->addStat(new DashboardStat(
            key: 'comments.pending',
            label: 'Pending comments',
            count: fn (): int => Comment::query()->withStatus(CommentStatus::Pending)->count(),
            order: 70,
            capability: Capability::ModerateComments,
            route: 'admin.comments.index',
            parameters: ['status' => CommentStatus::Pending->value],
            icon: 'comments',
        ));

        $this->app->make(Dashboard::class)->addPanel(new DashboardPanel(
            key: 'pending-comments',
            label: 'Pending comments',
            items: (new PendingCommentsPanel((int) config('verso.admin.dashboard_items')))(...),
            icon: 'comments',
            emptyText: 'No comments are waiting for review.',
            order: 20,
            capability: Capability::ModerateComments,
            route: 'admin.comments.index',
            parameters: ['status' => CommentStatus::Pending->value],
        ));
    }

    // The current value of the switch: the entry meta, or the default for a new entry.
    protected function commentsOpen(mixed $entry): bool
    {
        return $entry instanceof Entry
            ? $this->app->make(CommentAvailability::class)->entryAllows($entry)
            : (bool) config('verso.comments.open_by_default');
    }

    // A "Comments" column in the admin entries list, counted in the list query itself.
    protected function registerListColumn(): void
    {
        $filters = $this->app->make(FilterRegistry::class);

        $filters->add(AdminEntryColumnsFilter::class, function (array $columns): array {
            $column = new ListColumn('comments', __('Comments'), 'number', '2xl', withCount: ['comments']);
            $keys = array_values(array_map(fn (ListColumn $item): string => $item->key, $columns));
            $position = array_search('word_count', $keys, true);

            array_splice($columns, is_int($position) ? $position : count($columns), 0, [$column]);

            return $columns;
        });

        $filters->add(AdminEntryRowFilter::class, fn (array $row, Entry $entry): array => [
            ...$row,
            'columns' => [...(array) ($row['columns'] ?? []), 'comments' => (int) ($entry->getAttribute('comments_count') ?? 0)],
        ]);
    }
}
