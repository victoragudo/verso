<?php

namespace App\Verso\Easy\Styles;

// What saving "Make it yours" did: tokens the theme refused and colors that are hard to read.
final readonly class EasyStyleResult
{
    /**
     * @param  list<string>  $rejected  token ids that were not saved
     * @param  list<array{key: string, foreground: string, background: string, ratio: float, minimum: float, fix: string, direction: string, message: string}>  $warnings
     */
    public function __construct(
        public array $rejected = [],
        public array $warnings = [],
    ) {}

    public function message(): string
    {
        return $this->warnings === []
            ? __('Your new look is saved. Visitors see it now.')
            : __('Your new look is saved, but some colors are hard to read: :warning', ['warning' => $this->warnings[0]['message']]);
    }
}
