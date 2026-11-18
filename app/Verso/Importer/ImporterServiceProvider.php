<?php

namespace App\Verso\Importer;

use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Core\Settings\Settings;
use App\Verso\Importer\Console\ImportWordPressCommand;
use App\Verso\Importer\Http\Middleware\RedirectLegacyQueries;
use App\Verso\Importer\Support\SeoMetaMapper;
use App\Verso\Importer\Support\WordPressDates;
use App\Verso\Importer\WordPress\AttachmentImporter;
use App\Verso\Importer\WordPress\AuthorImporter;
use App\Verso\Importer\WordPress\EntryImporter;
use App\Verso\Importer\WordPress\RedirectWriter;
use App\Verso\Importer\WordPress\WordPressImporter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;

// Boots the WordPress importer: its services, command, admin page and old URL redirects.
class ImporterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfiguredServices();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportWordPressCommand::class]);
        }

        AdminRoutes::load(base_path('routes/verso/importer.php'));

        $this->app->booted(function (): void {
            AdminNav::add(new NavItem(
                key: 'importer',
                label: 'Import',
                route: 'admin.import.index',
                icon: 'importer',
                order: 30,
                capability: Capability::ImportContent,
                group: NavGroup::Administration,
                keywords: ['wordpress', 'wxr', 'migrate', 'importar'],
            ));
        });

        // Global, so old links work even before a theme adds the home route.
        $this->app->make(HttpKernel::class)->pushMiddleware(RedirectLegacyQueries::class);
    }

    // Give the importer classes their values from config/verso.php.
    protected function registerConfiguredServices(): void
    {
        $this->app->bind(WordPressDates::class, fn (Application $app): WordPressDates => new WordPressDates(
            (string) $app->make(Settings::class)->get('site.timezone', 'UTC'),
        ));

        $this->app->when(SeoMetaMapper::class)->needs('$separator')->giveConfig('verso.importer.seo_separator');

        $this->app->when(AuthorImporter::class)->needs(Role::class)
            ->give(fn (): Role => Role::from((string) config('verso.importer.author_role')));
        $this->app->when(AuthorImporter::class)->needs('$passwordLength')->giveConfig('verso.importer.password_length');
        $this->app->when(AuthorImporter::class)->needs('$fallbackDomain')->giveConfig('verso.importer.fallback_email_domain');

        $this->app->when(AttachmentImporter::class)->needs('$disk')->giveConfig('verso.importer.media_disk');
        $this->app->when(AttachmentImporter::class)->needs('$directory')->giveConfig('verso.importer.media_directory');
        $this->app->when(AttachmentImporter::class)->needs('$enabled')->giveConfig('verso.importer.download_attachments');
        $this->app->when(AttachmentImporter::class)->needs('$timeout')->giveConfig('verso.importer.download_timeout');
        $this->app->when(AttachmentImporter::class)->needs('$retries')->giveConfig('verso.importer.download_retries');
        $this->app->when(AttachmentImporter::class)->needs('$retryDelay')->giveConfig('verso.importer.download_retry_delay');
        $this->app->when(AttachmentImporter::class)->needs('$maxBytes')->giveConfig('verso.importer.max_download_bytes');

        $this->app->when(EntryImporter::class)->needs('$skipTypes')->giveConfig('verso.importer.skip_post_types');
        $this->app->when(EntryImporter::class)->needs('$skipStatuses')->giveConfig('verso.importer.skip_statuses');
        $this->app->when(EntryImporter::class)->needs('$excerptMaxLength')->giveConfig('verso.content.excerpt_max_length');

        $this->app->when(RedirectWriter::class)->needs('$dateRedirects')->giveConfig('verso.importer.date_redirects');

        $this->app->when(WordPressImporter::class)->needs('$messageLimit')->giveConfig('verso.importer.report_message_limit');
    }
}
