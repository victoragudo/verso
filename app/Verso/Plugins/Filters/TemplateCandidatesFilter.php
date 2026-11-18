<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Theme\Templates\TemplateContext;

// Changes the list of theme views tried for a page. Callbacks: fn (array $candidates, TemplateContext $context, ?object $subject): array.
final class TemplateCandidatesFilter implements Filter
{
    /**
     * @param  list<string>  $candidates
     * @return array<mixed>
     */
    public static function apply(array $candidates, TemplateContext $context, ?object $subject = null): array
    {
        return (array) app(FilterRegistry::class)->apply(self::class, $candidates, $context, $subject);
    }
}
