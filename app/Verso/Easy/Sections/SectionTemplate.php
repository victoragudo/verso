<?php

namespace App\Verso\Easy\Sections;

use Closure;
use InvalidArgumentException;

// One ready section (hero, services, FAQ...): what the gallery shows about it and how it turns content into blocks.
final readonly class SectionTemplate
{
    // Keys look like "hero" or "shop-products".
    public const KEY_PATTERN = '/^[a-z][a-z0-9-]{0,40}$/';

    /**
     * @param  Closure(SectionContent, SectionKit): list<array<string, mixed>>  $builder  makes the blocks from the content
     * @param  list<string>  $keywords  extra search words for the gallery
     * @param  string|null  $plugin  slug of a plugin the section needs, or null
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public Closure $builder,
        public string $icon = 'patterns',
        public string $category = 'content',
        public array $keywords = [],
        public ?string $plugin = null,
        public SectionContent $defaults = new SectionContent,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("The section key \"{$key}\" must start with a letter and use only a-z, 0-9 and -.");
        }
    }

    /**
     * The blocks of this section. Missing content takes the defaults, every block gets a fresh id.
     *
     * @param  SectionContent|array<string, mixed>  $content
     * @return list<array<string, mixed>>
     */
    public function build(SectionContent|array $content = [], ?SectionKit $kit = null): array
    {
        $content = ($content instanceof SectionContent ? $content : SectionContent::from($content))->withDefaults($this->defaults);

        return ($this->builder)($content, $kit ?? app(SectionKit::class));
    }

    /**
     * What the section gallery needs to list this section.
     *
     * @return array{key: string, label: string, description: string, icon: string, category: string, keywords: list<string>, plugin: string|null, defaults: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'category' => $this->category,
            'keywords' => $this->keywords,
            'plugin' => $this->plugin,
            'defaults' => $this->defaults->toArray(),
        ];
    }
}
