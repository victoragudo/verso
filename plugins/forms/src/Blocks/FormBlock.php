<?php

namespace Verso\Plugins\Forms\Blocks;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;
use Verso\Plugins\Forms\FormsServiceProvider;
use Verso\Plugins\Forms\Models\Form;
use Verso\Plugins\Forms\View\FormRenderer;

// A block that shows one of the forms built in the admin.
class FormBlock implements EmbeddableBlock, HasBlockFields, PluginBlock
{
    // Select value used before any form exists. Empty values are not allowed in selects.
    public const NO_FORM = 'none';

    public function name(): string
    {
        return FormsServiceProvider::BLOCK;
    }

    public function label(): string
    {
        return __('forms::Form');
    }

    public function description(): string
    {
        return __('forms::A contact form, or any other form built under Forms.');
    }

    public function shortcode(): bool
    {
        return true;
    }

    public function keyAttribute(): ?string
    {
        return 'form';
    }

    public function attributeAliases(): array
    {
        return ['slug' => 'form'];
    }

    public function capability(): ?string
    {
        return null;
    }

    public function fields(): array
    {
        $forms = Form::query()->orderBy('title')->orderBy('id')->pluck('title', 'slug')->all();

        return [
            new BlockField(
                name: 'form',
                label: __('forms::Form'),
                type: BlockFieldType::Select,
                default: array_key_first($forms) ?? self::NO_FORM,
                options: $forms === [] ? [self::NO_FORM => __('forms::No forms yet')] : $forms,
                help: __('forms::Build and change forms under Forms in the sidebar.'),
                required: true,
                searchable: true,
            ),
            new BlockField(name: 'title', label: __('forms::Heading'), max: 120),
            new BlockField(name: 'text', label: __('forms::Text above the form'), type: BlockFieldType::Textarea, max: 500),
        ];
    }

    public function render(array $block, ?Entry $entry = null): string
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $text = fn (string $key): string => is_string($attrs[$key] ?? null) ? trim($attrs[$key]) : '';
        $slug = $text('form');

        if ($slug === '' || $slug === self::NO_FORM) {
            return '';
        }

        return app(FormRenderer::class)->render(
            Form::query()->where('slug', $slug)->first(),
            $text('title'),
            $text('text'),
        );
    }
}
