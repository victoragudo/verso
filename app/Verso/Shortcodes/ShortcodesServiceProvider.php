<?php

namespace App\Verso\Shortcodes;

use App\Verso\Content\Events\EntrySaved;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Shortcodes\Console\ShortcodesCommand;
use App\Verso\Shortcodes\Usage\EmbedInvalidator;
use App\Verso\Shortcodes\Usage\UsageIndex;
use App\Verso\Shortcodes\View\EmbedComponent;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

// Boots shortcodes: the expander for text blocks, the Blade embed, the usage index and the command.
class ShortcodesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShortcodeRegistry::class);
        $this->app->singleton(ShortcodeExpander::class);
        $this->app->singleton(UsageIndex::class);
        $this->app->singleton(EmbedInvalidator::class, fn (Application $app): EmbedInvalidator => new EmbedInvalidator(
            usage: $app->make(UsageIndex::class),
            pages: $app->make(PageCache::class),
            targeted: (bool) config('verso.shortcodes.targeted_invalidation'),
        ));

        $this->app->afterResolving(BlockRenderer::class, function (BlockRenderer $renderer, Application $app): void {
            $renderer->setInlineExpander($app->make(ShortcodeExpander::class));
        });
    }

    public function boot(): void
    {
        Blade::component('verso-embed', EmbedComponent::class);

        // @shortcodes($text, 'excerpt') escapes a plain text and expands its shortcodes, when that place is turned on.
        Blade::directive('shortcodes', fn (string $expression): string => '<?php echo app(\\'.ShortcodeExpander::class."::class)->expandPlace({$expression}); ?>");

        Event::listen(EntrySaved::class, fn (EntrySaved $event) => $this->app->make(UsageIndex::class)->refresh($event->entry));

        if ($this->app->runningInConsole()) {
            $this->commands([ShortcodesCommand::class]);
        }
    }
}
