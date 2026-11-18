<?php

namespace Verso\Plugins\Newsletter;

use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Sections\SectionContent;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionTemplate;
use App\Verso\Plugins\Filters\PrivatePathsFilter;
use App\Verso\Plugins\PluginServiceProvider;
use App\Verso\Shortcodes\Usage\EmbedInvalidator;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\ServerLimit;
use App\Verso\System\Config\Unit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Verso\Plugins\Newsletter\Blocks\SignupBlock;
use Verso\Plugins\Newsletter\Jobs\RecordQueueHeartbeat;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Newsletter\Support\QueueHeartbeat;

// Boots the Newsletter plugin: signup block, public links, campaigns and admin pages.
class NewsletterServiceProvider extends PluginServiceProvider
{
    // The block type stored in entries.
    public const BLOCK = 'newsletter-signup';

    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/newsletter.php'), 'newsletter');
        $this->registerSystemSettings('Newsletter', 'newsletter', ...$this->systemSettings());
        $this->app->singleton(NewsletterSettings::class);
        $this->app->bind(QueueHeartbeat::class, fn ($app): QueueHeartbeat => new QueueHeartbeat(
            settings: $app->make(Settings::class),
            staleMinutes: (int) config('newsletter.heartbeat_stale_minutes'),
        ));
    }

    public function boot(): void
    {
        $this->loadPluginMigrations();
        $this->loadPluginViews('newsletter');
        $this->loadThemeViews();
        $this->loadAdminRoutes('routes/admin.php');

        RateLimiter::for('newsletter-signup', fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('newsletter.signup_rate_limit')))->by((string) $request->ip()));

        if (! $this->app->routesAreCached()) {
            Route::group([], $this->path('routes/public.php'));
        }

        $this->registerBlock(new SignupBlock);
        $this->registerSection(new SectionTemplate(
            key: 'newsletter',
            label: 'Newsletter signup',
            description: 'A short line and a form to join your newsletter.',
            builder: fn (SectionContent $content, SectionKit $kit): array => [$kit->section([
                $kit->block(self::BLOCK, ['title' => $content->heading, 'text' => $content->text, 'button' => $content->extraText('button', 'Subscribe'), 'list' => SignupBlock::NO_LIST]),
            ], ['color' => ['background' => SectionKit::COLOR_SURFACE], 'border' => ['radius' => SectionKit::RADIUS], 'spacing' => ['padding' => [
                'left' => SectionKit::SPACE_LARGE, 'right' => SectionKit::SPACE_LARGE,
            ]]], $content->heading)],
            icon: 'newsletter',
            category: 'action',
            keywords: ['newsletter', 'email', 'subscribe', 'signup'],
            plugin: 'newsletter',
            defaults: new SectionContent(heading: 'Get the newsletter', text: 'News and offers once a month. Leave any time.'),
        ));

        // A renamed list changes the signup forms that point to it.
        $listChanged = fn (MailingList $list) => $this->app->make(EmbedInvalidator::class)->changed(
            NewsletterServiceProvider::BLOCK,
            (string) $list->getOriginal('slug'),
            $list->slug,
        );
        MailingList::saved($listChanged);
        MailingList::deleted($listChanged);

        // Confirm, unsubscribe and tracking links belong to one subscriber, so stats leave them out.
        $this->addFilter(PrivatePathsFilter::class, fn (array $patterns): array => [...$patterns, '/newsletter']);

        $this->registerHeartbeat();
    }

    // Puts a heartbeat job on the newsletter queue on a schedule. Nothing is needed with the sync driver.
    protected function registerHeartbeat(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $job = new RecordQueueHeartbeat;
            $queue = (string) config('newsletter.queue');

            if ($queue !== '') {
                $job->onQueue($queue);
            }

            $schedule->job($job)
                ->cron((string) config('newsletter.heartbeat_schedule'))
                ->name('newsletter:queue-heartbeat');
        });
    }

    // Limits of this plugin that admins can change in System settings.
    /** @return list<ConfigParameter> */
    protected function systemSettings(): array
    {
        $group = $this->systemGroup();

        return [
            new ConfigParameter('newsletter.import_max_kb', $group, 'Largest subscriber CSV', 'Biggest CSV file the subscriber import accepts.', unit: Unit::Kilobytes, min: 16, max: 1_048_576, env: 'NEWSLETTER_IMPORT_MAX_KB', limits: ServerLimit::uploads()),
            new ConfigParameter('newsletter.confirm_hours', $group, 'Confirmation links expire after', 'How long a double opt-in link stays valid.', unit: Unit::Hours, min: 1, max: 720, env: 'NEWSLETTER_CONFIRM_HOURS'),
            new ConfigParameter('newsletter.signup_rate_limit', $group, 'Signups per minute', 'Signups allowed each minute from one IP address.', unit: Unit::Requests, min: 1, max: 1000, env: 'NEWSLETTER_SIGNUP_RATE_LIMIT'),
            new ConfigParameter('newsletter.per_page', $group, 'Rows per page', 'Rows on one admin page.', unit: Unit::Items, min: 5, max: 200, env: 'NEWSLETTER_PER_PAGE'),
            new ConfigParameter('newsletter.heartbeat_stale_minutes', $group, 'Queue worker warning after', 'Time without a queue heartbeat before the admin warns that no worker runs.', unit: Unit::Minutes, min: 5, max: 1440, env: 'NEWSLETTER_HEARTBEAT_STALE_MINUTES'),
        ];
    }
}
