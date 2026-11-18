<?php

namespace App\Verso\Editor\Builder;

use App\Verso\Editor\Styles\BlockStyleSchema;
use App\Verso\Editor\Styles\StylePreset;
use App\Verso\Editor\Styles\StylePresets;

// The builder items of core. They use the same register() calls that plugins and themes use.
class CoreBuilderItems
{
    // Block groups of the "Add" panel. Plugin blocks without a group go to "plugins".
    public const array CATEGORIES = [
        'text' => ['label' => 'Text', 'order' => 10],
        'media' => ['label' => 'Media', 'order' => 20],
        'interactive' => ['label' => 'Interactive', 'order' => 25],
        'layout' => ['label' => 'Layout', 'order' => 30],
        'site' => ['label' => 'Site', 'order' => 40],
        'dynamic' => ['label' => 'Page data', 'order' => 45],
        'advanced' => ['label' => 'Advanced', 'order' => 50],
        'plugins' => ['label' => 'From plugins', 'order' => 60],
    ];

    // Features with a plain name and a short help text for the "Editor settings" page.
    public const array FEATURES = [
        'add_panel' => ['label' => 'Add panel and drag and drop', 'description' => 'Drag blocks, sections and patterns from a side panel into the page.'],
        'list_view' => ['label' => 'Outline', 'description' => 'A tree of every block on the page, to select and move blocks.'],
        'styles_panel' => ['label' => 'Styles', 'description' => 'Change spacing, colors, fonts, borders and more for each block.'],
        'element_block' => ['label' => 'HTML elements', 'description' => 'Add any safe HTML element, like a div, nav or details, with its own attributes.'],
        'code_editor' => ['label' => 'Code view', 'description' => 'Edit the whole page as HTML code.'],
        'custom_css' => ['label' => 'Custom CSS', 'description' => 'Add your own CSS to a page or to the whole site.'],
        'custom_js' => ['label' => 'Custom JavaScript', 'description' => 'Add your own JavaScript to a page or to the whole site.'],
    ];

    // Plain names of the core style options in the Styles panel.
    public const array STYLE_LABELS = [
        'spacing.margin.top' => ['label' => 'Margin top'],
        'spacing.margin.right' => ['label' => 'Margin right'],
        'spacing.margin.bottom' => ['label' => 'Margin bottom'],
        'spacing.margin.left' => ['label' => 'Margin left'],
        'spacing.padding.top' => ['label' => 'Padding top'],
        'spacing.padding.right' => ['label' => 'Padding right'],
        'spacing.padding.bottom' => ['label' => 'Padding bottom'],
        'spacing.padding.left' => ['label' => 'Padding left'],
        'size.width' => ['label' => 'Width'],
        'size.minWidth' => ['label' => 'Min width'],
        'size.height' => ['label' => 'Height'],
        'size.maxHeight' => ['label' => 'Max height'],
        'size.maxWidth' => ['label' => 'Max width'],
        'size.minHeight' => ['label' => 'Min height'],
        'layout.display' => ['label' => 'Display'],
        'layout.direction' => ['label' => 'Direction'],
        'layout.wrap' => ['label' => 'Wrap'],
        'layout.gap' => ['label' => 'Gap'],
        'layout.align' => ['label' => 'Align'],
        'layout.justify' => ['label' => 'Justify'],
        'layout.columns' => ['label' => 'Columns'],
        'typography.fontFamily' => ['label' => 'Font family'],
        'typography.fontSize' => ['label' => 'Font size'],
        'typography.fontWeight' => ['label' => 'Font weight'],
        'typography.lineHeight' => ['label' => 'Line height'],
        'typography.letterSpacing' => ['label' => 'Letter spacing'],
        'typography.transform' => ['label' => 'Transform'],
        'color.text' => ['label' => 'Text'],
        'color.background' => ['label' => 'Background'],
        'color.gradient' => ['label' => 'Gradient'],
        'color.overlay' => ['label' => 'Overlay'],
        'background.image' => ['label' => 'Picture'],
        'background.position' => ['label' => 'Focal point'],
        'background.size' => ['label' => 'Picture size'],
        'background.repeat' => ['label' => 'Repeat'],
        'background.attachment' => ['label' => 'Scrolling'],
        'border.width' => ['label' => 'Width'],
        'border.style' => ['label' => 'Style'],
        'border.radius' => ['label' => 'Radius'],
        'border.color' => ['label' => 'Color'],
        'shadow' => ['label' => 'Shadow'],
        'position.sticky' => ['label' => 'Sticky'],
        'position.top' => ['label' => 'Top'],
        'position.right' => ['label' => 'Right'],
        'position.bottom' => ['label' => 'Bottom'],
        'position.left' => ['label' => 'Left'],
        'position.zIndex' => ['label' => 'Stack order'],
        'visibility.hidden' => ['label' => 'Hidden'],
    ];

    // Style presets every site starts with. They use theme tokens, so they follow the site style.
    public const array PRESETS = [
        'card' => ['label' => 'Card', 'design' => [
            'spacing' => ['padding' => ['top' => 'var:spacing.medium', 'right' => 'var:spacing.medium', 'bottom' => 'var:spacing.medium', 'left' => 'var:spacing.medium']],
            'color' => ['background' => 'var:color.surface'],
            'border' => ['width' => '1px', 'style' => 'solid', 'color' => '#7f7f7f40', 'radius' => 'var:radius.base'],
        ]],
        'hero' => ['label' => 'Hero', 'design' => [
            'spacing' => ['padding' => [
                'top' => ['base' => 'var:spacing.x-large', 'mobile' => 'var:spacing.large'],
                'bottom' => ['base' => 'var:spacing.x-large', 'mobile' => 'var:spacing.large'],
                'left' => 'var:spacing.medium',
                'right' => 'var:spacing.medium',
            ]],
            'size' => ['minHeight' => ['base' => '60vh', 'mobile' => '40vh']],
            'layout' => ['display' => 'flex', 'direction' => 'column', 'justify' => 'center', 'gap' => 'var:spacing.medium'],
            'background' => ['size' => 'cover', 'position' => '50% 50%'],
        ]],
        'pill' => ['label' => 'Pill', 'design' => [
            'spacing' => ['padding' => ['top' => '0.25rem', 'bottom' => '0.25rem', 'left' => '0.75rem', 'right' => '0.75rem']],
            'border' => ['radius' => '999px'],
            'color' => ['background' => 'var:color.surface'],
            'typography' => ['fontSize' => '0.875rem'],
        ]],
    ];

    public function registerPresets(StylePresets $presets): void
    {
        foreach (self::PRESETS as $key => ['label' => $label, 'design' => $design]) {
            $presets->register(new StylePreset(key: $key, label: $label, design: $design));
        }
    }

    public function registerCategories(BlockCategoryRegistry $categories): void
    {
        foreach (self::CATEGORIES as $key => ['label' => $label, 'order' => $order]) {
            $categories->register(new BlockCategory($key, $label, $order));
        }
    }

    public function registerStyles(StylePropertyRegistry $styles): void
    {
        foreach (BlockStyleSchema::OPTIONS as $path => [$property, $kind]) {
            $styles->register(StyleProperty::core($path, $property, $kind, self::STYLE_LABELS[$path]['label']));
        }
    }

    /** @param  array<string, list<string>>  $roles  feature => roles that have it by default */
    public function registerFeatures(EditorFeatureRegistry $features, array $roles): void
    {
        foreach (self::FEATURES as $key => ['label' => $label, 'description' => $description]) {
            $features->register(new EditorFeature($key, $label, $description, $roles[$key] ?? []));
        }
    }
}
