<?php

namespace Verso\Plugins\BuilderExample;

use App\Verso\Editor\Builder\BlockCategory;
use App\Verso\Editor\Builder\BlockCategoryRegistry;
use App\Verso\Editor\Builder\DropRuleRegistry;
use App\Verso\Editor\Builder\StyleProperty;
use App\Verso\Editor\Builder\StylePropertyRegistry;
use App\Verso\Plugins\PluginServiceProvider;
use Verso\Plugins\BuilderExample\Blocks\NoticeBlock;

// Example of every builder extension point. The client part is in resources/js/builder.tsx.
class BuilderExampleServiceProvider extends PluginServiceProvider
{
    // Block type stored in entries.
    public const string BLOCK = 'example-notice';

    // Group of the block in the Add panel.
    public const string CATEGORY = 'examples';

    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/builder-example.php'), 'builder-example');
    }

    public function boot(): void
    {
        // 1. A custom block.
        $this->registerBlock(new NoticeBlock(array_values(array_map(strval(...), (array) config('builder-example.tones')))));

        // 2. Its own group in the Add panel.
        $this->app->make(BlockCategoryRegistry::class)->register(new BlockCategory(self::CATEGORY, 'Examples', 55));

        // 3. A style property. It shows in the Styles panel and renders as CSS on the site.
        $this->app->make(StylePropertyRegistry::class)->register(new StyleProperty(
            path: 'effects.opacity',
            cssProperty: 'opacity',
            kind: 'number',
            label: 'Opacity',
            group: 'effects',
            control: 'number',
        ));

        // A size style property. Its kind gives it every angle unit; a CssValueRule can limit units or add keywords.
        $this->app->make(StylePropertyRegistry::class)->register(new StyleProperty(
            path: 'effects.rotate',
            cssProperty: 'rotate',
            kind: 'angle',
            label: 'Rotate',
            group: 'effects',
            control: 'length',
        ));

        // 4. A drop rule, checked when saving and rendering. The client file adds the same rule for dragging.
        $this->app->make(DropRuleRegistry::class)->register('builder-example/no-notice-in-columns', new NoNoticeInColumnsRule);
    }
}
