<?php

namespace App\Verso\Core\Admin;

// An optional column of an admin list. The user can show or hide it with the column chooser.
final readonly class ListColumn
{
    // From which list width the column shows when the user did not pick columns. Matches the UI.
    public const DEFAULTS = ['always', 'md', 'lg', 'xl', '2xl', '3xl', 'never'];

    // How the UI shows the value.
    public const KINDS = ['text', 'number', 'date', 'image', 'tags', 'seo', 'money', 'status'];

    /**
     * @param  list<string>  $with  relations the list loads for this column in one query, like "shopProduct.variants"
     * @param  list<string>  $withCount  relations the list counts in its main query, like "comments" (gives comments_count)
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $kind = 'text',
        public string $defaultFrom = 'xl',
        public array $with = [],
        public array $withCount = [],
    ) {}

    /**
     * @return array{key: string, label: string, kind: string, defaultFrom: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'kind' => in_array($this->kind, self::KINDS, true) ? $this->kind : 'text',
            'defaultFrom' => in_array($this->defaultFrom, self::DEFAULTS, true) ? $this->defaultFrom : 'xl',
        ];
    }
}
