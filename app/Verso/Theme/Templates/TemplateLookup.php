<?php

namespace App\Verso\Theme\Templates;

// The result of one template lookup: every candidate with its file (or null) and the view that was chosen.
final readonly class TemplateLookup
{
    /**
     * @param  list<array{name: string, file: string|null, checked: bool}>  $candidates
     */
    public function __construct(
        public TemplateContext $context,
        public array $candidates,
        public ?string $chosen,
    ) {}

    public function found(): bool
    {
        return $this->chosen !== null;
    }
}
