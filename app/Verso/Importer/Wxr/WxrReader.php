<?php

namespace App\Verso\Importer\Wxr;

use DOMElement;
use DOMNode;
use Generator;
use XMLReader;

// Reads a WordPress export (WXR) file as a stream, so big files use little memory.
class WxrReader
{
    protected const CONTENT_NS = 'http://purl.org/rss/1.0/modules/content/';

    protected const DC_NS = 'http://purl.org/dc/elements/1.1/';

    protected const WP_NS_PREFIX = 'http://wordpress.org/export/';

    // Depth of the elements inside <rss><channel>.
    protected const RECORD_DEPTH = 2;

    protected const READER_OPTIONS = LIBXML_NONET | LIBXML_PARSEHUGE | LIBXML_COMPACT;

    public function __construct(protected string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidWxrFile(__('The file :path cannot be read.', ['path' => $path]));
        }
    }

    // The site facts at the top of the file. Fails when the file is not a WordPress export.
    public function channel(): WxrChannel
    {
        $fields = [];

        $this->stream(function (XMLReader $reader, string $name) use (&$fields): bool {
            if ($name === 'item') {
                return false;
            }

            if (in_array($name, ['title', 'link', 'wp:base_site_url', 'wp:base_blog_url', 'wp:wxr_version'], true)) {
                $fields[$name] = trim($reader->readString());
            }

            return true;
        });

        if (($fields['wp:wxr_version'] ?? '') === '') {
            throw new InvalidWxrFile(__('This file is not a WordPress export (WXR) file.'));
        }

        return new WxrChannel(
            title: $fields['title'] ?? '',
            link: $fields['link'] ?? '',
            baseSiteUrl: $fields['wp:base_site_url'] ?? '',
            baseBlogUrl: $fields['wp:base_blog_url'] ?? '',
            wxrVersion: $fields['wp:wxr_version'],
        );
    }

    /**
     * Every author, term and item in file order.
     *
     * @return Generator<int, WxrAuthor|WxrTerm|WxrItem>
     */
    public function records(): Generator
    {
        $reader = $this->open();

        try {
            $ok = $this->read($reader);

            while ($ok) {
                $name = $this->recordName($reader);
                $record = match ($name) {
                    'item', 'wp:author', 'wp:category', 'wp:tag', 'wp:term' => $this->expand($reader, $name),
                    default => null,
                };

                if ($record !== null) {
                    yield $record;
                    $ok = $this->next($reader);

                    continue;
                }

                $ok = $this->read($reader);
            }
        } finally {
            $reader->close();
        }
    }

    // Count the <item> elements without parsing them.
    public function countItems(): int
    {
        $count = 0;

        $this->stream(function (XMLReader $reader, string $name) use (&$count): bool {
            if ($name === 'item') {
                $count++;
            }

            return true;
        });

        return $count;
    }

    // Walk the elements inside the channel. The callback returns false to stop.
    /** @param callable(XMLReader, string): bool $visit */
    protected function stream(callable $visit): void
    {
        $reader = $this->open();

        try {
            $ok = $this->read($reader);

            while ($ok) {
                $name = $this->recordName($reader);

                if ($name !== null) {
                    if (! $visit($reader, $name)) {
                        return;
                    }

                    $ok = $this->next($reader);

                    continue;
                }

                $ok = $this->read($reader);
            }
        } finally {
            $reader->close();
        }
    }

    protected function open(): XMLReader
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $reader = XMLReader::open($this->path, null, self::READER_OPTIONS);

        if ($reader === false) {
            throw new InvalidWxrFile(__('The file :path cannot be opened.', ['path' => $this->path]));
        }

        return $reader;
    }

    protected function read(XMLReader $reader): bool
    {
        libxml_clear_errors();

        return $this->checked($reader, $reader->read());
    }

    protected function next(XMLReader $reader): bool
    {
        libxml_clear_errors();

        return $this->checked($reader, $reader->next());
    }

    // Turn libxml errors into an exception with a clear message.
    protected function checked(XMLReader $reader, bool $moved): bool
    {
        $error = libxml_get_last_error();

        if ($error !== false && $error->level >= LIBXML_ERR_ERROR) {
            libxml_clear_errors();
            $reader->close();

            throw new InvalidWxrFile(__('The export file is broken near line :line: :error', ['line' => $error->line, 'error' => trim($error->message)]));
        }

        return $moved;
    }

    // The prefixed name of an element right inside the channel, or null for anything else.
    protected function recordName(XMLReader $reader): ?string
    {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->depth !== self::RECORD_DEPTH) {
            return null;
        }

        return $this->prefixFor($reader->namespaceURI).$reader->localName;
    }

    protected function expand(XMLReader $reader, string $name): WxrAuthor|WxrTerm|WxrItem|null
    {
        $node = $reader->expand();

        if (! $node instanceof DOMElement) {
            return null;
        }

        return match ($name) {
            'item' => $this->item($node),
            'wp:author' => $this->author($node),
            'wp:category' => $this->category($node),
            'wp:tag' => $this->tag($node),
            default => $this->term($node),
        };
    }

    // Same short prefixes for every WXR version: "wp:", "content:", "excerpt:" and "dc:".
    protected function prefixFor(?string $namespace): string
    {
        $namespace = (string) $namespace;

        return match (true) {
            $namespace === '' => '',
            $namespace === self::CONTENT_NS => 'content:',
            $namespace === self::DC_NS => 'dc:',
            str_starts_with($namespace, self::WP_NS_PREFIX) && str_contains($namespace, '/excerpt/') => 'excerpt:',
            str_starts_with($namespace, self::WP_NS_PREFIX) => 'wp:',
            default => 'other:',
        };
    }

    /**
     * The child elements of a node, grouped by prefixed name.
     *
     * @return array<string, list<DOMElement>>
     */
    protected function children(DOMNode $node): array
    {
        $children = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[$this->prefixFor($child->namespaceURI).$child->localName][] = $child;
            }
        }

        return $children;
    }

    /** @param array<string, list<DOMElement>> $children */
    protected function text(array $children, string $name): string
    {
        return isset($children[$name][0]) ? $children[$name][0]->textContent : '';
    }

    /** @param array<string, list<DOMElement>> $children */
    protected function int(array $children, string $name): int
    {
        return (int) trim($this->text($children, $name));
    }

    // WordPress keeps some names with HTML entities, like "Food &amp; Drink".
    protected function decoded(string $text): string
    {
        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    protected function author(DOMElement $node): WxrAuthor
    {
        $children = $this->children($node);

        return new WxrAuthor(
            id: $this->int($children, 'wp:author_id'),
            login: trim($this->text($children, 'wp:author_login')),
            email: trim($this->text($children, 'wp:author_email')),
            displayName: trim($this->text($children, 'wp:author_display_name')),
            firstName: trim($this->text($children, 'wp:author_first_name')),
            lastName: trim($this->text($children, 'wp:author_last_name')),
        );
    }

    protected function category(DOMElement $node): WxrTerm
    {
        $children = $this->children($node);

        return new WxrTerm(
            taxonomy: 'category',
            slug: trim($this->text($children, 'wp:category_nicename')),
            name: $this->decoded($this->text($children, 'wp:cat_name')),
            parentSlug: trim($this->text($children, 'wp:category_parent')),
            description: $this->decoded($this->text($children, 'wp:category_description')),
        );
    }

    protected function tag(DOMElement $node): WxrTerm
    {
        $children = $this->children($node);

        return new WxrTerm(
            taxonomy: 'post_tag',
            slug: trim($this->text($children, 'wp:tag_slug')),
            name: $this->decoded($this->text($children, 'wp:tag_name')),
            description: $this->decoded($this->text($children, 'wp:tag_description')),
        );
    }

    protected function term(DOMElement $node): WxrTerm
    {
        $children = $this->children($node);

        return new WxrTerm(
            taxonomy: trim($this->text($children, 'wp:term_taxonomy')),
            slug: trim($this->text($children, 'wp:term_slug')),
            name: $this->decoded($this->text($children, 'wp:term_name')),
            parentSlug: trim($this->text($children, 'wp:term_parent')),
            description: $this->decoded($this->text($children, 'wp:term_description')),
        );
    }

    protected function item(DOMElement $node): WxrItem
    {
        $children = $this->children($node);

        return new WxrItem(
            id: $this->int($children, 'wp:post_id'),
            postType: trim($this->text($children, 'wp:post_type')),
            title: $this->decoded($this->text($children, 'title')),
            slug: rawurldecode(trim($this->text($children, 'wp:post_name'))),
            status: trim($this->text($children, 'wp:status')),
            link: trim($this->text($children, 'link')),
            content: $this->text($children, 'content:encoded'),
            excerpt: $this->text($children, 'excerpt:encoded'),
            creator: trim($this->text($children, 'dc:creator')),
            date: trim($this->text($children, 'wp:post_date')),
            dateGmt: trim($this->text($children, 'wp:post_date_gmt')),
            parentId: $this->int($children, 'wp:post_parent'),
            menuOrder: $this->int($children, 'wp:menu_order'),
            attachmentUrl: trim($this->text($children, 'wp:attachment_url')),
            terms: $this->itemTerms($children['category'] ?? []),
            meta: $this->itemMeta($children['wp:postmeta'] ?? []),
            comments: array_map($this->comment(...), $children['wp:comment'] ?? []),
        );
    }

    /**
     * @param  list<DOMElement>  $nodes
     * @return list<WxrTerm>
     */
    protected function itemTerms(array $nodes): array
    {
        $terms = [];

        foreach ($nodes as $node) {
            $taxonomy = $node->getAttribute('domain');
            $slug = rawurldecode($node->getAttribute('nicename'));

            if ($taxonomy !== '' && $slug !== '') {
                $terms[] = new WxrTerm(taxonomy: $taxonomy, slug: $slug, name: $this->decoded($node->textContent));
            }
        }

        return $terms;
    }

    /**
     * @param  list<DOMElement>  $nodes
     * @return array<string, string>
     */
    protected function itemMeta(array $nodes): array
    {
        $meta = [];

        foreach ($nodes as $node) {
            $children = $this->children($node);
            $key = trim($this->text($children, 'wp:meta_key'));

            if ($key !== '') {
                $meta[$key] = $this->text($children, 'wp:meta_value');
            }
        }

        return $meta;
    }

    protected function comment(DOMElement $node): WxrComment
    {
        $children = $this->children($node);

        return new WxrComment(
            id: $this->int($children, 'wp:comment_id'),
            authorName: trim($this->text($children, 'wp:comment_author')),
            authorEmail: trim($this->text($children, 'wp:comment_author_email')),
            authorUrl: trim($this->text($children, 'wp:comment_author_url')),
            authorIp: trim($this->text($children, 'wp:comment_author_IP')),
            date: trim($this->text($children, 'wp:comment_date')),
            dateGmt: trim($this->text($children, 'wp:comment_date_gmt')),
            content: $this->text($children, 'wp:comment_content'),
            approved: trim($this->text($children, 'wp:comment_approved')),
            type: trim($this->text($children, 'wp:comment_type')),
            parentId: $this->int($children, 'wp:comment_parent'),
            userId: $this->int($children, 'wp:comment_user_id'),
        );
    }
}
