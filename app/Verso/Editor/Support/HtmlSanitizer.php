<?php

namespace App\Verso\Editor\Support;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

// Cleans HTML against an allow list. Unknown tags are unwrapped, dangerous tags are removed with their content.
class HtmlSanitizer
{
    // Inline tags allowed inside text blocks.
    public const array INLINE_TAGS = ['strong', 'b', 'em', 'i', 'u', 's', 'code', 'a', 'br', 'sub', 'sup', 'mark'];

    // Tags removed together with everything inside them.
    protected const array DROPPED_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'template', 'noscript',
        'noembed', 'noframes', 'textarea', 'title', 'svg', 'math', 'select', 'option', 'head', 'meta', 'link',
        'base', 'form', 'input', 'button', 'xmp', 'plaintext', 'video', 'audio', 'img', 'picture', 'source',
        'canvas', 'dialog',
    ];

    // Tags that never have a closing tag.
    protected const array VOID_TAGS = ['br'];

    public function __construct(protected UrlSanitizer $urls) {}

    // Keeps only inline marks: bold, italic, links, code and a few more.
    public function inline(string $html): string
    {
        return $this->clean($html);
    }

    /**
     * Cleans HTML. Extra tags can be allowed, and some tags can be removed (but not their content) at the top level.
     *
     * @param  list<string>  $extraTags
     * @param  list<string>  $unwrapAtRoot
     */
    public function clean(string $html, array $extraTags = [], array $unwrapAtRoot = []): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html, LIBXML_NOERROR);
        $body = $document->body;

        if ($body === null) {
            return '';
        }

        $allowed = array_flip(array_merge(self::INLINE_TAGS, $extraTags));

        return $this->children($body, $allowed, array_flip($unwrapAtRoot));
    }

    // Plain text of some HTML, with tags removed and entities decoded.
    public function text(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html, LIBXML_NOERROR);

        return $document->body === null ? '' : $this->textOf($document->body);
    }

    /**
     * @param  array<string, int>  $allowed
     * @param  array<string, int>  $unwrap
     */
    protected function children(Node $parent, array $allowed, array $unwrap = []): string
    {
        $html = '';

        foreach ($parent->childNodes as $node) {
            $html .= $this->node($node, $allowed, $unwrap);
        }

        return $html;
    }

    /**
     * @param  array<string, int>  $allowed
     * @param  array<string, int>  $unwrap
     */
    protected function node(Node $node, array $allowed, array $unwrap): string
    {
        if ($node instanceof Text) {
            return htmlspecialchars($node->data, ENT_NOQUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (! $node instanceof Element) {
            return '';
        }

        $tag = strtolower($node->localName);

        if (in_array($tag, self::DROPPED_TAGS, true) || $node->namespaceURI !== 'http://www.w3.org/1999/xhtml') {
            return '';
        }

        if (! isset($allowed[$tag]) || isset($unwrap[$tag])) {
            return $this->children($node, $allowed);
        }

        if (in_array($tag, self::VOID_TAGS, true)) {
            return "<{$tag}>";
        }

        return '<'.$tag.$this->attributes($node, $tag).'>'.$this->children($node, $allowed)."</{$tag}>";
    }

    // Every attribute is dropped, except a safe href on links.
    protected function attributes(Element $element, string $tag): string
    {
        if ($tag !== 'a') {
            return '';
        }

        $href = $this->urls->clean($element->getAttribute('href'));

        return $href === null ? '' : ' href="'.e($href).'"';
    }

    protected function textOf(Node $parent): string
    {
        $text = '';

        foreach ($parent->childNodes as $node) {
            if ($node instanceof Text) {
                $text .= $node->data;
            } elseif ($node instanceof Element && ! in_array(strtolower($node->localName), self::DROPPED_TAGS, true)) {
                $text .= strtolower($node->localName) === 'br' ? "\n" : $this->textOf($node);
            }
        }

        return $text;
    }
}
