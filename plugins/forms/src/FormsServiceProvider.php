<?php

namespace Verso\Plugins\Forms;

use App\Verso\Plugins\Contracts\SeedsDemoContent;
use App\Verso\Plugins\Filters\PrivatePathsFilter;
use App\Verso\Plugins\PluginServiceProvider;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\ServerLimit;
use App\Verso\System\Config\Unit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Verso\Plugins\Forms\Blocks\FormBlock;
use Verso\Plugins\Forms\Console\PruneCommand;
use Verso\Plugins\Forms\Jobs\PruneOldSubmissions;
use Verso\Plugins\Forms\Support\DemoContactForm;
use Verso\Plugins\Forms\Support\FormsSettings;
use Verso\Plugins\Forms\View\FormComponent;
use Verso\Plugins\Forms\View\FormRenderer;

// Boots the Forms plugin: the Form block, the Blade component, public endpoints and the admin pages.
class FormsServiceProvider extends PluginServiceProvider implements SeedsDemoContent
{
    // The block type stored in entries.
    public const BLOCK = 'form';

    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/forms.php'), 'forms');
        $this->registerSystemSettings('Forms', 'forms', ...$this->systemSettings());
        $this->app->singleton(FormsSettings::class);
        $this->app->scoped(FormRenderer::class);
    }

    public function boot(): void
    {
        $this->loadPluginMigrations();
        $this->loadPluginViews('forms');
        $this->loadAdminRoutes('routes/admin.php');

        // A coarse limit per IP on the submit route. SubmitForm adds a finer limit per form.
        RateLimiter::for('forms-submit', fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('forms.spam.rate_limit') * 2))->by((string) $request->ip()));

        if (! $this->app->routesAreCached()) {
            Route::group([], $this->path('routes/public.php'));
        }

        $this->registerBlock(new FormBlock);

        // People in the simple admin mode still find the messages of their contact form.
        $this->allowInSimpleMode('plugin.forms', 'forms.messages');
        Blade::component('forms-form', FormComponent::class);

        // The token and submit endpoints are not pages, so stats leave them out.
        $this->addFilter(PrivatePathsFilter::class, fn (array $patterns): array => [...$patterns, '/forms']);

        if ($this->app->runningInConsole()) {
            $this->commands([PruneCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new PruneOldSubmissions)
                ->cron((string) config('forms.prune_schedule'))
                ->name('forms:prune');
        });
    }

    public function seedDemoContent(): void
    {
        $this->app->make(DemoContactForm::class)->seed();
    }

    // Limits of this plugin that admins can change in System settings.
    /** @return list<ConfigParameter> */
    protected function systemSettings(): array
    {
        $group = $this->systemGroup();

        return [
            new ConfigParameter('forms.upload_max_kb', $group, 'Largest file a form may allow', 'Highest file size a form builder can pick for a file field.', unit: Unit::Kilobytes, min: 16, max: 4_194_304, env: 'FORMS_UPLOAD_MAX_KB', limits: ServerLimit::uploads()),
            new ConfigParameter('forms.upload_default_kb', $group, 'Default file size of a file field', 'File size a new file field starts with.', unit: Unit::Kilobytes, min: 16, max: 4_194_304, env: 'FORMS_UPLOAD_DEFAULT_KB'),
            new ConfigParameter('forms.spam.rate_limit', $group, 'Messages per visitor', 'Messages one visitor can send in each window.', unit: Unit::Requests, min: 1, max: 1000, env: 'FORMS_RATE_LIMIT'),
            new ConfigParameter('forms.spam.rate_limit_minutes', $group, 'Message window', 'Length of the window for the limit above.', unit: Unit::Minutes, min: 1, max: 1440, env: 'FORMS_RATE_LIMIT_MINUTES'),
            new ConfigParameter('forms.max_fields', $group, 'Most fields in a form', 'Most fields one form may have.', unit: Unit::Items, min: 1, max: 200, env: 'FORMS_MAX_FIELDS'),
            new ConfigParameter('forms.textarea_max', $group, 'Longest long text answer', 'Longest text a textarea accepts when the form sets no limit.', unit: Unit::Characters, min: 100, max: 100_000, env: 'FORMS_TEXTAREA_MAX'),
            new ConfigParameter('forms.per_page', $group, 'Rows per page', 'Rows on one admin page of the inbox.', unit: Unit::Items, min: 5, max: 200, env: 'FORMS_PER_PAGE'),
            new ConfigParameter('forms.captcha.timeout', $group, 'Captcha check time limit', 'How long Verso waits for the captcha service.', unit: Unit::Seconds, min: 1, max: 60, env: 'FORMS_CAPTCHA_TIMEOUT'),
        ];
    }
}
