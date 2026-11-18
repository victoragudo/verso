<?php

namespace App\Verso\Theme\Templates;

// An ordered list of theme view names to try for one page. The first view that exists is used.
final readonly class TemplateCandidates
{
    // View names: letters, digits, "_" and "-", in parts split by "." or "/".
    public const NAME_PATTERN = '/^[A-Za-z0-9_-]+(?:[.\/][A-Za-z0-9_-]+)*$/';

    /** @var list<string> */
    public array $names;

    /**
     * @param  array<mixed>  $names
     */
    public function __construct(
        public TemplateContext $context,
        array $names,
        // What the page shows: an entry, a term, a content type or an author.
        public ?object $subject = null,
    ) {
        $this->names = self::clean($names);
    }

    // A list with one view, for pages that ask for a fixed view.
    public static function single(TemplateContext $context, string $name): self
    {
        return new self($context, [$name]);
    }

    /**
     * Keep only safe, unique view names, in their first order.
     *
     * @param  array<mixed>  $names
     * @return list<string>
     */
    public static function clean(array $names): array
    {
        $clean = [];

        foreach ($names as $name) {
            if (is_string($name) && preg_match(self::NAME_PATTERN, $name) === 1 && ! in_array($name, $clean, true)) {
                $clean[] = $name;
            }
        }

        return $clean;
    }
}
