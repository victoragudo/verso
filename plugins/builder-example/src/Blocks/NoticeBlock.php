<?php

namespace Verso\Plugins\BuilderExample\Blocks;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\HasBlockCategory;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;
use Verso\Plugins\BuilderExample\BuilderExampleServiceProvider;

// A colored box with a short message, like "Closed on public holidays".
class NoticeBlock implements HasBlockCategory, HasBlockFields, PluginBlock
{
    /** @param list<string> $tones */
    public function __construct(protected array $tones) {}

    public function name(): string
    {
        return BuilderExampleServiceProvider::BLOCK;
    }

    public function label(): string
    {
        return __('builder-example::Notice');
    }

    public function description(): string
    {
        return __('builder-example::A colored box with a short message.');
    }

    public function category(): string
    {
        return BuilderExampleServiceProvider::CATEGORY;
    }

    public function fields(): array
    {
        return [
            new BlockField(name: 'message', label: __('builder-example::Message'), type: BlockFieldType::Text, required: true, max: 200),
            new BlockField(
                name: 'tone',
                label: __('builder-example::Tone'),
                type: BlockFieldType::Select,
                options: array_combine($this->tones, array_map($this->toneLabel(...), $this->tones)),
                default: $this->tones[0] ?? 'info',
            ),
        ];
    }

    protected function toneLabel(string $tone): string
    {
        return match ($tone) {
            'info' => __('builder-example::Info'),
            'success' => __('builder-example::Success'),
            'warning' => __('builder-example::Warning'),
            default => ucfirst($tone),
        };
    }

    /** @param array<mixed> $block */
    public function render(array $block, ?Entry $entry = null): string
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $message = is_string($attrs['message'] ?? null) ? trim($attrs['message']) : '';
        $tone = in_array($attrs['tone'] ?? null, $this->tones, true) ? $attrs['tone'] : ($this->tones[0] ?? 'info');

        return $message === '' ? '' : '<p class="example-notice example-notice--'.e($tone).'" role="note">'.e($message).'</p>';
    }
}
