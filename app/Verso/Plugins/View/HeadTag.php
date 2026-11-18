<?php

namespace App\Verso\Plugins\View;

use InvalidArgumentException;

// One tag for the <head> of public pages: meta, link, script or style. Attributes are escaped.
final readonly class HeadTag
{
    /** Elements a plugin may add to the head. */
    public const ELEMENTS = ['meta', 'link', 'script', 'style'];

    /** Elements without a closing tag. */
    protected const VOID_ELEMENTS = ['meta', 'link'];

    /**
     * @param  array<string, string|bool|int|null>  $attributes  true prints a bare attribute, false and null skip it
     */
    public function __construct(
        public string $element,
        public array $attributes = [],
        public ?string $content = null,
    ) {
        if (! in_array($element, self::ELEMENTS, true)) {
            throw new InvalidArgumentException("The head tag <{$element}> is not allowed.");
        }
    }

    /** @param  array<string, string|bool|int|null>  $attributes */
    public static function meta(array $attributes): self
    {
        return new self('meta', $attributes);
    }

    /** @param  array<string, string|bool|int|null>  $attributes */
    public static function link(array $attributes): self
    {
        return new self('link', $attributes);
    }

    // A script file, or an inline script when $code is given.
    /** @param  array<string, string|bool|int|null>  $attributes */
    public static function script(?string $src = null, array $attributes = [], ?string $code = null): self
    {
        return new self('script', $src === null ? $attributes : ['src' => $src, ...$attributes], $code);
    }

    public static function style(string $css): self
    {
        return new self('style', [], $css);
    }

    public function toHtml(): string
    {
        $html = '<'.$this->element.$this->attributeHtml().'>';

        if (in_array($this->element, self::VOID_ELEMENTS, true)) {
            return $html;
        }

        return $html.$this->safeContent().'</'.$this->element.'>';
    }

    protected function attributeHtml(): string
    {
        $html = '';

        foreach ($this->attributes as $name => $value) {
            if ($value === null || $value === false || preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:.-]*$/', $name) !== 1) {
                continue;
            }

            $html .= $value === true ? ' '.$name : ' '.$name.'="'.e((string) $value).'"';
        }

        return $html;
    }

    // Inline code may not close its own tag early.
    protected function safeContent(): string
    {
        return (string) preg_replace('#</('.$this->element.')#i', '<\/$1', (string) $this->content);
    }
}
