<?php

namespace App\Verso\Blocks;

use App\Verso\Blocks\Dynamic\ArchiveTitleBlock;
use App\Verso\Blocks\Dynamic\BreadcrumbsBlock;
use App\Verso\Blocks\Dynamic\PostAuthorBlock;
use App\Verso\Blocks\Dynamic\PostContentBlock;
use App\Verso\Blocks\Dynamic\PostDateBlock;
use App\Verso\Blocks\Dynamic\PostExcerptBlock;
use App\Verso\Blocks\Dynamic\PostFeaturedImageBlock;
use App\Verso\Blocks\Dynamic\PostFieldBlock;
use App\Verso\Blocks\Dynamic\PostTermsBlock;
use App\Verso\Blocks\Dynamic\PostTitleBlock;
use App\Verso\Blocks\Dynamic\QueryLoopBlock;
use App\Verso\Blocks\Dynamic\ReadingTimeBlock;
use App\Verso\Blocks\Interactive\AccordionBlock;
use App\Verso\Blocks\Interactive\AccordionItemBlock;
use App\Verso\Blocks\Interactive\AlertBlock;
use App\Verso\Blocks\Interactive\CountdownBlock;
use App\Verso\Blocks\Interactive\CounterBlock;
use App\Verso\Blocks\Interactive\IconBlock;
use App\Verso\Blocks\Interactive\IconListBlock;
use App\Verso\Blocks\Interactive\IconListItemBlock;
use App\Verso\Blocks\Interactive\ImageCompareBlock;
use App\Verso\Blocks\Interactive\MegaMenuBlock;
use App\Verso\Blocks\Interactive\MobileMenuBlock;
use App\Verso\Blocks\Interactive\ModalBlock;
use App\Verso\Blocks\Interactive\ProgressBarBlock;
use App\Verso\Blocks\Interactive\SlideBlock;
use App\Verso\Blocks\Interactive\SliderBlock;
use App\Verso\Blocks\Interactive\SocialShareBlock;
use App\Verso\Blocks\Interactive\TabBlock;
use App\Verso\Blocks\Interactive\TableOfContentsBlock;
use App\Verso\Blocks\Interactive\TabsBlock;
use App\Verso\Blocks\Interactive\TimelineBlock;
use App\Verso\Blocks\Interactive\TimelineItemBlock;
use App\Verso\Blocks\Interactive\ToggleBlock;
use App\Verso\Blocks\Interactive\VideoBackgroundBlock;
use App\Verso\Blocks\Layout\GridBlock;
use App\Verso\Blocks\Layout\ShapeDividerBlock;
use App\Verso\Blocks\Layout\SpacerBlock;
use App\Verso\Blocks\Layout\StackBlock;
use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\Assets\BlockAssetRegistry;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\Blocks\GalleryBlock;
use App\Verso\Editor\Icons\IconLibrary;
use App\Verso\Plugins\Blocks\PluginBlockAdapter;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Filters\EntryContentFilter;
use Illuminate\Support\ServiceProvider;

// Boots the builder blocks: layout (grid, stack, spacer, shape divider) and interactive blocks (tabs, slider,
// counter...). Each block is one class; its CSS and JavaScript live in resources/blocks/<asset> and load only
// on pages that show it. Plugins add blocks the same way, through the plugin block registry.
class BlocksServiceProvider extends ServiceProvider
{
    /** @var list<class-string<ModuleBlock>> */
    public const array BLOCKS = [
        GridBlock::class, StackBlock::class, SpacerBlock::class, ShapeDividerBlock::class,
        TabsBlock::class, TabBlock::class, AccordionBlock::class, AccordionItemBlock::class, ToggleBlock::class,
        SliderBlock::class, SlideBlock::class, ImageCompareBlock::class, VideoBackgroundBlock::class,
        IconBlock::class, IconListBlock::class, IconListItemBlock::class, CounterBlock::class, ProgressBarBlock::class,
        TimelineBlock::class, TimelineItemBlock::class, AlertBlock::class, CountdownBlock::class,
        SocialShareBlock::class, TableOfContentsBlock::class, ModalBlock::class, MegaMenuBlock::class, MobileMenuBlock::class,
        PostTitleBlock::class, PostFeaturedImageBlock::class, PostContentBlock::class, PostExcerptBlock::class,
        PostDateBlock::class, PostAuthorBlock::class, PostTermsBlock::class, PostFieldBlock::class,
        ReadingTimeBlock::class, BreadcrumbsBlock::class, ArchiveTitleBlock::class, QueryLoopBlock::class,
    ];

    // Folder of the block assets.
    public const string ASSETS = 'resources/blocks';

    // Shared styles of every builder block: hidden text for screen readers and icon sizes.
    public const string BASE = 'base';

    public function register(): void
    {
        $this->app->singleton(IconLibrary::class, fn (): IconLibrary => new IconLibrary(base_path(self::ASSETS.'/icons/icons.json')));
    }

    public function boot(): void
    {
        $plugins = $this->app->make(PluginBlockRegistry::class);
        $blocks = $this->app->make(BlockRegistry::class);
        $assets = $this->app->make(BlockAssetRegistry::class);

        foreach (self::BLOCKS as $class) {
            self::add($this->app->make($class));
        }

        self::registerAsset($assets, self::BASE, [GalleryBlock::LIGHTBOX]);
        self::registerAsset($assets, GalleryBlock::LIGHTBOX, [GalleryBlock::LIGHTBOX]);
        $assets->register('icon-set', json: base_path(self::ASSETS.'/icons/icons.json'));

        // A table of contents lists the headings of the whole entry, so it is filled after the content is drawn.
        $this->app->make(FilterRegistry::class)->add(EntryContentFilter::class, fn (string $html, Entry $entry): string => TableOfContentsBlock::fill($html));
    }

    // Adds one module block to both editors and the renderer, with its CSS and JavaScript. Other modules use it too.
    public static function add(ModuleBlock $block): void
    {
        app(PluginBlockRegistry::class)->register($block);
        app(BlockRegistry::class)->register(new PluginBlockAdapter($block));

        foreach ([self::BASE, ...$block->assets()] as $key) {
            self::registerAsset(app(BlockAssetRegistry::class), $key, [$block->name()]);
        }
    }

    /** @param list<string> $blocks */
    public static function registerAsset(BlockAssetRegistry $assets, string $key, array $blocks): void
    {
        $folder = base_path(self::ASSETS.'/'.$key);
        $css = $folder.'/'.$key.'.css';
        $js = $folder.'/'.$key.'.js';

        $assets->register(
            $key,
            css: is_file($css) ? $css : null,
            js: is_file($js) ? $js : null,
            blocks: $blocks,
        );
    }
}
