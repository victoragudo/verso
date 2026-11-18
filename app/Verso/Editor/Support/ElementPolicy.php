<?php

namespace App\Verso\Editor\Support;

use App\Verso\I18n\Translate;

// One place that decides which HTML elements and attributes the element block may use.
// Dangerous tags (script, iframe, style...), event handlers (on*) and unsafe URLs are never allowed here;
// they stay limited to the raw HTML block and its capability.
class ElementPolicy
{
    // Elements people can pick, with a plain name for the editor.
    public const array TAGS = [
        'div' => ['label' => 'Box (div)'],
        'section' => ['label' => 'Section'],
        'article' => ['label' => 'Article'],
        'aside' => ['label' => 'Side note (aside)'],
        'header' => ['label' => 'Header'],
        'footer' => ['label' => 'Footer'],
        'nav' => ['label' => 'Navigation (nav)'],
        'main' => ['label' => 'Main content'],
        'span' => ['label' => 'Inline text (span)'],
        'figure' => ['label' => 'Figure'],
        'figcaption' => ['label' => 'Figure caption'],
        'details' => ['label' => 'Details (opens and closes)'],
        'summary' => ['label' => 'Summary (title of details)'],
        'blockquote' => ['label' => 'Quote (blockquote)'],
        'ul' => ['label' => 'List (ul)'],
        'ol' => ['label' => 'Numbered list (ol)'],
        'li' => ['label' => 'List item (li)'],
        'p' => ['label' => 'Paragraph (p)'],
        'h1' => ['label' => 'Heading 1'],
        'h2' => ['label' => 'Heading 2'],
        'h3' => ['label' => 'Heading 3'],
        'h4' => ['label' => 'Heading 4'],
        'h5' => ['label' => 'Heading 5'],
        'h6' => ['label' => 'Heading 6'],
        'a' => ['label' => 'Link (a)'],
        'small' => ['label' => 'Small text'],
        'mark' => ['label' => 'Highlight (mark)'],
        'time' => ['label' => 'Date or time'],
        'address' => ['label' => 'Contact address'],
    ];

    // Attributes every element may have. data-* and aria-* are checked by pattern.
    protected const array GLOBAL_ATTRIBUTES = ['id', 'class', 'role', 'lang', 'title', 'dir'];

    // Attributes only some elements may have.
    protected const array ELEMENT_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'time' => ['datetime'],
        'details' => ['open'],
        'blockquote' => ['cite'],
        'ol' => ['start', 'reversed'],
    ];

    // Value checks by attribute name. Names not listed accept plain text.
    protected const array PATTERNS = [
        'id' => '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/',
        'class' => '/^[A-Za-z0-9_-]{1,64}( [A-Za-z0-9_-]{1,64}){0,19}$/',
        'role' => '/^[a-z]{2,30}$/',
        'lang' => '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8}){0,3}$/',
        'dir' => '/^(ltr|rtl|auto)$/',
        'target' => '/^(_blank|_self)$/',
        'rel' => '/^[a-z]{2,20}( [a-z]{2,20}){0,5}$/',
        'datetime' => '/^[0-9T:+\-. Z]{4,40}$/',
        'start' => '/^-?\d{1,6}$/',
        'open' => '/^(open|)$/',
        'reversed' => '/^(reversed|)$/',
    ];

    // A data-* or aria-* attribute name.
    protected const string CUSTOM_NAME = '/^(data-[a-z0-9]+(-[a-z0-9]+)*|aria-[a-z]{2,24})$/';

    // Attributes holding a URL.
    protected const array URL_ATTRIBUTES = ['href', 'cite'];

    public function __construct(
        protected UrlSanitizer $urls,
        protected int $maxAttributes,
        protected int $maxValueLength,
    ) {}

    public function allowsTag(mixed $tag): bool
    {
        return is_string($tag) && isset(self::TAGS[$tag]);
    }

    // True when the element may carry this attribute.
    public function allowsAttribute(string $tag, string $name): bool
    {
        return in_array($name, self::GLOBAL_ATTRIBUTES, true)
            || in_array($name, self::ELEMENT_ATTRIBUTES[$tag] ?? [], true)
            || preg_match(self::CUSTOM_NAME, $name) === 1;
    }

    /**
     * The first problem with the attributes of an element, or null when they are fine.
     *
     * @param  mixed  $attributes  name => value
     */
    public function attributeError(string $tag, mixed $attributes): ?string
    {
        if ($attributes === null || $attributes === []) {
            return null;
        }

        if (! is_array($attributes) || array_is_list($attributes)) {
            return Translate::text('The attributes must be a list of names and values.');
        }

        if (count($attributes) > $this->maxAttributes) {
            return Translate::icu('{count, plural, one {An element can have at most # attribute.} other {An element can have at most # attributes.}}', ['count' => $this->maxAttributes]);
        }

        foreach ($attributes as $name => $value) {
            $name = (string) $name;

            if (! $this->allowsAttribute($tag, $name)) {
                return Translate::text('The attribute ":name" is not allowed on <:tag>. Use the HTML block for it.', ['name' => $name, 'tag' => $tag]);
            }

            if (! is_string($value) || mb_strlen($value) > $this->maxValueLength || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                return Translate::text('The value of ":name" must be a short single line of text.', ['name' => $name]);
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && $this->urls->clean($value) === null) {
                return Translate::text('The address in ":name" is not allowed.', ['name' => $name]);
            }

            if (isset(self::PATTERNS[$name]) && preg_match(self::PATTERNS[$name], $value) !== 1) {
                return Translate::text('The value of ":name" is not valid.', ['name' => $name]);
            }
        }

        return null;
    }

    /**
     * The attributes as escaped HTML. Anything not allowed is left out.
     *
     * @param  mixed  $attributes  name => value
     */
    public function render(string $tag, mixed $attributes, string $baseClass): string
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $html = '';
        $class = $baseClass;

        foreach ($attributes as $name => $value) {
            $name = (string) $name;

            if (! is_string($value) || $this->attributeError($tag, [$name => $value]) !== null) {
                continue;
            }

            if ($name === 'class') {
                $class .= ' '.$value;

                continue;
            }

            if ($name === 'target' && $value === '_blank' && ! isset($attributes['rel'])) {
                $html .= ' rel="noopener"';
            }

            $html .= in_array($name, ['open', 'reversed'], true)
                ? ' '.$name
                : ' '.$name.'="'.e(in_array($name, self::URL_ATTRIBUTES, true) ? (string) $this->urls->clean($value) : $value).'"';
        }

        return ' class="'.e($class).'"'.$html;
    }

    /** @return list<array{value: string, label: string}> */
    public function tagOptions(): array
    {
        return array_map(
            fn (string $tag, array $option): array => ['value' => $tag, 'label' => Translate::text($option['label'])],
            array_keys(self::TAGS),
            self::TAGS,
        );
    }
}
