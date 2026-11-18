<?php

namespace App\Verso\Seo\Audit;

// How to fix an issue: a link to the right screen, or a one click action.
final readonly class IssueFix
{
    public const LINK = 'link';

    public const ACTION = 'action';

    public function __construct(
        public string $label,
        public string $kind = self::LINK,
        public ?string $url = null,
    ) {}

    public static function link(string $label, string $url): self
    {
        return new self($label, self::LINK, $url);
    }

    public static function action(string $label): self
    {
        return new self($label, self::ACTION);
    }

    /** @return array{label: string, kind: string, url: string|null} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'kind' => $this->kind, 'url' => $this->url];
    }
}
