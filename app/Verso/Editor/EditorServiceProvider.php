<?php

namespace App\Verso\Editor;

use App\Verso\Access\Events\RoleCreated;
use App\Verso\Access\Events\RoleDeleted;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\Assets\BlockAssetRegistry;
use App\Verso\Editor\Blocks\ButtonBlock;
use App\Verso\Editor\Blocks\CodeBlock;
use App\Verso\Editor\Blocks\ColumnBlock;
use App\Verso\Editor\Blocks\ColumnsBlock;
use App\Verso\Editor\Blocks\ElementBlock;
use App\Verso\Editor\Blocks\EmbedBlock;
use App\Verso\Editor\Blocks\GalleryBlock;
use App\Verso\Editor\Blocks\HeadingBlock;
use App\Verso\Editor\Blocks\HtmlBlock;
use App\Verso\Editor\Blocks\ImageBlock;
use App\Verso\Editor\Blocks\ListBlock;
use App\Verso\Editor\Blocks\ParagraphBlock;
use App\Verso\Editor\Blocks\QuoteBlock;
use App\Verso\Editor\Blocks\SectionBlock;
use App\Verso\Editor\Blocks\SeparatorBlock;
use App\Verso\Editor\Blocks\TableBlock;
use App\Verso\Editor\Blocks\VideoBlock;
use App\Verso\Editor\Builder\BlockCategoryRegistry;
use App\Verso\Editor\Builder\CoreBuilderItems;
use App\Verso\Editor\Builder\DropRuleRegistry;
use App\Verso\Editor\Builder\EditorAccess;
use App\Verso\Editor\Builder\EditorFeatureRegistry;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Editor\Builder\ParentDropRule;
use App\Verso\Editor\Builder\StylePropertyRegistry;
use App\Verso\Editor\Builder\ThemeEditorLimits;
use App\Verso\Editor\Contracts\Block;
use App\Verso\Editor\CustomCode\CustomCodeAccess;
use App\Verso\Editor\CustomCode\CustomCodeOutput;
use App\Verso\Editor\CustomCode\CustomCodeStore;
use App\Verso\Editor\CustomCode\StylesheetSanitizer;
use App\Verso\Editor\Styles\BlockStyleCompiler;
use App\Verso\Editor\Styles\BlockStyleSchema;
use App\Verso\Editor\Styles\StylePresetChanged;
use App\Verso\Editor\Styles\StylePresetInvalidator;
use App\Verso\Editor\Styles\StylePresets;
use App\Verso\Editor\Support\ElementPolicy;
use App\Verso\Editor\Support\EmbedResolver;
use App\Verso\Editor\Support\HtmlSanitizer;
use App\Verso\Editor\Support\RenderContext;
use App\Verso\Editor\Support\UrlSanitizer;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Filters\EntryContentFilter;
use App\Verso\Plugins\Filters\HeadTagsFilter;
use App\Verso\Plugins\Filters\ThemeFooterFilter;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\ThemeManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Boots the editor module: the block registry, the core blocks and the HTML renderer.
class EditorServiceProvider extends ServiceProvider
{
    /** @var list<class-string<Block>> */
    public const array CORE_BLOCKS = [
        ParagraphBlock::class, HeadingBlock::class, ListBlock::class, QuoteBlock::class, CodeBlock::class,
        ImageBlock::class, GalleryBlock::class, EmbedBlock::class, ColumnsBlock::class, ColumnBlock::class,
        SeparatorBlock::class, ButtonBlock::class, HtmlBlock::class, TableBlock::class, VideoBlock::class,
        SectionBlock::class, ElementBlock::class,
    ];

    public function register(): void
    {
        $this->registerBuilder();

        $this->app->singleton(UrlSanitizer::class, fn (): UrlSanitizer => new UrlSanitizer(
            array_values(array_map(strval(...), (array) config('verso.editor.allowed_url_schemes'))),
        ));

        $this->app->singleton(StylesheetSanitizer::class, fn (): StylesheetSanitizer => new StylesheetSanitizer(
            maxLength: (int) config('verso.builder.custom_code.css_max'),
        ));
        $this->app->scoped(CustomCodeStore::class, fn (Application $app): CustomCodeStore => new CustomCodeStore(
            css: $app->make(StylesheetSanitizer::class),
            pages: $app->make(PageCache::class),
            maxJsLength: (int) config('verso.builder.custom_code.js_max'),
        ));
        $this->app->bind(CustomCodeAccess::class, fn (Application $app): CustomCodeAccess => new CustomCodeAccess(
            access: $app->make(EditorAccess::class),
            cssCapability: (string) config('verso.editor.custom_css_capability'),
            jsCapability: (string) config('verso.builder.custom_code.js_capability'),
        ));

        $this->app->singleton(ElementPolicy::class, fn (Application $app): ElementPolicy => new ElementPolicy(
            urls: $app->make(UrlSanitizer::class),
            maxAttributes: (int) config('verso.builder.element.max_attributes'),
            maxValueLength: (int) config('verso.builder.element.max_value_length'),
        ));

        $this->app->singleton(EmbedResolver::class, fn (): EmbedResolver => new EmbedResolver(
            youtubeUrl: (string) config('verso.editor.embed.youtube_url'),
            vimeoUrl: (string) config('verso.editor.embed.vimeo_url'),
        ));

        $this->app->singleton(BlockRegistry::class, function (Application $app): BlockRegistry {
            $registry = new BlockRegistry;

            foreach (self::CORE_BLOCKS as $block) {
                $registry->register($app->make($block));
            }

            return $registry;
        });

        $this->app->singleton(BlockRenderer::class, fn (Application $app): BlockRenderer => new BlockRenderer(
            registry: $app->make(BlockRegistry::class),
            sanitizer: $app->make(HtmlSanitizer::class),
            urls: $app->make(UrlSanitizer::class),
            maxDepth: (int) config('verso.editor.max_depth'),
            styles: $app->make(BlockStyleCompiler::class),
            dropRules: $app->make(DropRuleRegistry::class),
            assets: $app->make(BlockAssetRegistry::class),
        ));
        $this->app->singleton(BlockAssetRegistry::class);
        $this->app->scoped(RenderContext::class);
        $this->app->singleton(BlockStyleCompiler::class, fn (Application $app): BlockStyleCompiler => new BlockStyleCompiler(
            schema: $app->make(BlockStyleSchema::class),
            breakpoints: array_map(intval(...), (array) config('verso.editor.breakpoints')),
            maxCustomCss: (int) config('verso.editor.custom_css_max'),
            presets: $app->make(StylePresets::class),
        ));
        $this->app->scoped(StylePresets::class, function (Application $app): StylePresets {
            $presets = new StylePresets(
                settings: $app->make(Settings::class),
                themePresets: StylePresets::themeReader($app->make(ThemeManager::class)),
                maxSaved: (int) config('verso.editor.presets.max_saved'),
            );
            (new CoreBuilderItems)->registerPresets($presets);

            return $presets;
        });
    }

    public function boot(): void
    {
        // Copied roles get the editor tools of their source; deleted roles leave the lists.
        Event::listen(fn (RoleCreated $event) => $event->role->basedOn === null ? null : $this->app->make(EditorAccess::class)->copyRole($event->role->basedOn, $event->role->slug));
        Event::listen(fn (RoleDeleted $event) => $this->app->make(EditorAccess::class)->forgetRole($event->role->slug));

        // A changed preset changes the HTML of every page that uses it.
        Event::listen(StylePresetChanged::class, StylePresetInvalidator::class);

        AdminRoutes::load(base_path('routes/verso/editor.php'));
        Route::group([], base_path('routes/verso/editor-public.php'));
        $this->registerCustomCodeOutput();
        AdminNav::add(new NavItem(key: 'editor.code', label: 'Custom code', route: 'admin.editor.code.edit', icon: 'code', order: 40, capability: Capability::ManageSettings, group: NavGroup::Design, keywords: ['css', 'javascript', 'scripts', 'código']));
        AdminNav::add(new NavItem(key: 'editor.settings', label: 'Editor', route: 'admin.editor.settings.edit', icon: 'settings', order: 30, capability: Capability::ManageEditorSettings, group: NavGroup::Settings, keywords: ['blocks', 'builder', 'roles', 'editor settings']));
    }

    // Custom CSS and JavaScript on public pages, through the same filters plugins use.
    protected function registerCustomCodeOutput(): void
    {
        $filters = $this->app->make(FilterRegistry::class);
        $output = fn (): CustomCodeOutput => $this->app->make(CustomCodeOutput::class);

        $filters->add(HeadTagsFilter::class, fn (array $tags): array => $output()->headTags($tags));
        $filters->add(ThemeFooterFilter::class, fn (string $html): string => $output()->footer($html));
        $filters->add(EntryContentFilter::class, fn (string $html, Entry $entry): string => $output()->entryContent($html, $entry));
    }

    // The builder registries, filled with the core items. Plugins and themes add theirs in boot().
    protected function registerBuilder(): void
    {
        $core = new CoreBuilderItems;

        $this->app->singleton(DropRuleRegistry::class, function (): DropRuleRegistry {
            $rules = new DropRuleRegistry;
            $rules->register('core/parents', new ParentDropRule(fn (): BlockRegistry => $this->app->make(BlockRegistry::class)));

            return $rules;
        });
        $this->app->singleton(BlockCategoryRegistry::class, function () use ($core): BlockCategoryRegistry {
            $registry = new BlockCategoryRegistry;
            $core->registerCategories($registry);

            return $registry;
        });
        $this->app->singleton(StylePropertyRegistry::class, function () use ($core): StylePropertyRegistry {
            $registry = new StylePropertyRegistry;
            $core->registerStyles($registry);

            return $registry;
        });
        $this->app->singleton(EditorFeatureRegistry::class, function () use ($core): EditorFeatureRegistry {
            $registry = new EditorFeatureRegistry;
            $core->registerFeatures($registry, $this->featureRoles());

            return $registry;
        });
        $this->app->bind(BlockStyleSchema::class, fn (Application $app): BlockStyleSchema => new BlockStyleSchema($app->make(StylePropertyRegistry::class)));

        $this->app->bind(EditorManifest::class, fn (Application $app): EditorManifest => new EditorManifest(
            categories: $app->make(BlockCategoryRegistry::class),
            styles: $app->make(StylePropertyRegistry::class),
            access: $app->make(EditorAccess::class),
            theme: $app->make(ThemeEditorLimits::class),
            breakpoints: array_map(intval(...), (array) config('verso.editor.breakpoints')),
            recentLimit: (int) config('verso.builder.recent_limit'),
            undoSteps: (int) config('verso.builder.undo_steps'),
            undoMergeMs: (int) config('verso.builder.undo_merge_ms'),
            customCssMax: (int) config('verso.editor.custom_css_max'),
        ));
    }

    /** @return array<string, list<string>> feature => roles, from config */
    protected function featureRoles(): array
    {
        $roles = [];

        foreach ((array) config('verso.builder.features') as $feature => $list) {
            $roles[(string) $feature] = array_values(array_filter(array_map(trim(...), explode(',', (string) $list))));
        }

        return $roles;
    }
}
