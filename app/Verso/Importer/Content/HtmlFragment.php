<?php

namespace App\Verso\Importer\Content;

use DOMDocument;
use DOMElement;
use DOMNode;

// A small wrapper around DOMDocument to read pieces of post HTML.
class HtmlFragment
{
    protected const WRAPPER_ID = 'verso-fragment';

    protected function __construct(protected DOMDocument $document, protected DOMElement $root) {}

    public static function parse(string $html): self
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="'.self::WRAPPER_ID.'">'.$html.'</div>';
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById(self::WRAPPER_ID) ?? $document->documentElement;

        return new self($document, $root instanceof DOMElement ? $root : $document->createElement('div'));
    }

    // The first element with this tag name, or null.
    public function first(string ...$tags): ?DOMElement
    {
        foreach ($this->descendants($this->root) as $element) {
            if (in_array(strtolower($element->tagName), $tags, true)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Every element with this tag name, in document order.
     *
     * @return list<DOMElement>
     */
    public function all(string $tag): array
    {
        return array_values(array_filter(
            $this->descendants($this->root),
            fn (DOMElement $element): bool => strtolower($element->tagName) === $tag,
        ));
    }

    // The HTML inside the first matching element, or an empty string.
    public function innerHtml(string ...$tags): string
    {
        $element = $this->first(...$tags);

        return $element === null ? '' : $this->inner($element);
    }

    // The HTML inside an element.
    public function inner(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $this->document->saveHTML($child);
        }

        return trim($html);
    }

    public function text(): string
    {
        return trim($this->root->textContent);
    }

    // Remove every element with this tag name.
    public function remove(string $tag): self
    {
        foreach ($this->all($tag) as $element) {
            $element->parentNode?->removeChild($element);
        }

        return $this;
    }

    public function html(): string
    {
        return $this->inner($this->root);
    }

    /**
     * @return list<DOMElement>
     */
    protected function descendants(DOMElement $root): array
    {
        $elements = [];

        foreach ($root->getElementsByTagName('*') as $element) {
            $elements[] = $element;
        }

        return $elements;
    }
}
