<?php

namespace Verso\Plugins\Newsletter\Blocks;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Newsletter\NewsletterServiceProvider;

// A newsletter signup form that editors can drop into any entry.
class SignupBlock implements EmbeddableBlock, HasBlockFields, PluginBlock
{
    // Select value for "do not add to a list". Empty values are not allowed in selects.
    public const NO_LIST = 'none';

    // Heading levels the title may use. Entry titles are h1, so the default is h2.
    public const HEADING_LEVELS = ['h2', 'h3', 'h4'];

    public const DEFAULT_HEADING_LEVEL = 'h2';

    public function name(): string
    {
        return NewsletterServiceProvider::BLOCK;
    }

    public function label(): string
    {
        return __('newsletter::Newsletter signup');
    }

    public function description(): string
    {
        return __('newsletter::A form where visitors join the newsletter. They confirm by email.');
    }

    public function shortcode(): bool
    {
        return true;
    }

    public function keyAttribute(): ?string
    {
        return 'list';
    }

    public function attributeAliases(): array
    {
        return ['slug' => 'list'];
    }

    public function capability(): ?string
    {
        return null;
    }

    public function fields(): array
    {
        return [
            new BlockField(name: 'title', label: __('newsletter::Title'), default: 'Get the newsletter', max: 120),
            new BlockField(name: 'text', label: __('newsletter::Text'), type: BlockFieldType::Textarea, max: 500),
            new BlockField(name: 'button', label: __('newsletter::Button label'), default: 'Subscribe', max: 40),
            new BlockField(
                name: 'list',
                label: __('newsletter::List'),
                type: BlockFieldType::Select,
                default: self::NO_LIST,
                options: [self::NO_LIST => __('newsletter::No list'), ...MailingList::query()->orderBy('name')->pluck('name', 'slug')->all()],
            ),
            new BlockField(name: 'askName', label: __('newsletter::Ask for a name'), type: BlockFieldType::Toggle, default: false),
            new BlockField(
                name: 'headingLevel',
                label: __('newsletter::Title level'),
                type: BlockFieldType::Select,
                default: self::DEFAULT_HEADING_LEVEL,
                options: $this->headingLevelOptions(),
                help: __('newsletter::Pick the level that fits the headings around the form, so the page outline does not skip a level.'),
            ),
        ];
    }

    public function render(array $block, ?Entry $entry = null): string
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $text = fn (string $key, string $default = ''): string => is_string($attrs[$key] ?? null) && $attrs[$key] !== '' ? $attrs[$key] : $default;

        return view('newsletter::signup', [
            'title' => $text('title'),
            'text' => $text('text'),
            'button' => $text('button', __('newsletter::Subscribe')),
            'list' => $text('list') === self::NO_LIST ? '' : $text('list'),
            'askName' => (bool) ($attrs['askName'] ?? false),
            'headingTag' => $this->headingLevel($attrs['headingLevel'] ?? null),
            'formId' => 'newsletter-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($block['id'] ?? 'signup')),
        ])->render();
    }

    /**
     * Heading levels with labels in the current language.
     *
     * @return array<string, string>
     */
    protected function headingLevelOptions(): array
    {
        return [
            'h2' => __('newsletter::Heading 2'),
            'h3' => __('newsletter::Heading 3'),
            'h4' => __('newsletter::Heading 4'),
        ];
    }

    // A known heading tag from the attrs, else the default.
    protected function headingLevel(mixed $level): string
    {
        return is_string($level) && in_array($level, self::HEADING_LEVELS, true) ? $level : self::DEFAULT_HEADING_LEVEL;
    }
}
