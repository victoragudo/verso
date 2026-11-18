<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Fields\Theme\FieldPrinter;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// One custom field of the current entry, printed the same way as the field() helper of themes.
class PostFieldBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'post-field';
    }

    public function label(): string
    {
        return __('Custom field');
    }

    public function description(): string
    {
        return __('The value of a custom field of the post or page being shown.');
    }

    public function fields(): array
    {
        return [
            new BlockField('field', __('Field name'), BlockFieldType::Text, '', required: true, help: __('The name of the field, like "price" or "ingredients". Find it in Settings, Custom fields.'), max: 100),
            new BlockField('label', __('Label before the value'), BlockFieldType::Text, '', max: 80),
        ];
    }

    protected function forEntry(Entry $entry, BlockData $block, BlockRenderer $renderer): string
    {
        $name = $this->text($block, 'field');

        if (preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)*$/i', $name) !== 1) {
            return '';
        }

        $value = trim(app(FieldPrinter::class)->print(null, $name, $entry));

        if ($value === '') {
            return '';
        }

        $label = $this->text($block, 'label');

        return '<div class="verso-post-field">'.($label !== '' ? '<span class="verso-post-field__label">'.e($label).'</span> ' : '').$value.'</div>';
    }
}
