<?php

namespace App\Verso\Theme\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Media\ResponsiveImages;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Plugins\Filters\FeedItemsFilter;
use Illuminate\Support\Collection;
use XMLWriter;

// Writes the RSS 2.0 feed of the latest published entries.
class FeedBuilder
{
    public function __construct(
        protected Settings $settings,
        protected ContentRenderer $content,
        protected SiteTime $time,
        protected ResponsiveImages $images,
    ) {}

    /**
     * The newest published entries of a content type.
     *
     * @return Collection<int, Entry>
     */
    public function entries(string $type, int $limit): Collection
    {
        $entries = Entry::query()
            ->ofType($type)
            ->published()
            ->with('author')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->toBase();

        $this->images->preloadEntries($entries);

        return $entries;
    }

    /**
     * @param  iterable<Entry>  $entries
     */
    public function build(iterable $entries, string $feedUrl): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $xml->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');

        $xml->startElement('channel');
        $xml->writeElement('title', $this->text('site.name'));
        $xml->writeElement('link', url('/'));
        $xml->writeElement('description', $this->text('site.tagline'));
        $xml->writeElement('language', $this->text('site.locale'));
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $feedUrl);
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        $items = [];

        foreach ($entries as $entry) {
            $items[] = $this->itemFor($entry);
        }

        foreach (FeedItemsFilter::apply($items, $feedUrl) as $item) {
            $this->item($xml, $item);
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    public function itemFor(Entry $entry): FeedItem
    {
        return new FeedItem(
            title: $entry->title,
            link: $entry->url(),
            publishedAt: $entry->published_at ?? now(),
            content: $this->content->render($entry),
            description: $entry->excerpt,
            creator: $entry->author?->name,
            entry: $entry,
        );
    }

    protected function item(XMLWriter $xml, FeedItem $item): void
    {
        $xml->startElement('item');
        $xml->writeElement('title', $item->title);
        $xml->writeElement('link', $item->link);
        $xml->startElement('guid');
        $xml->writeAttribute('isPermaLink', 'true');
        $xml->text($item->link);
        $xml->endElement();
        $xml->writeElement('pubDate', $this->time->local($item->publishedAt)->toRssString());

        if ($item->creator !== null) {
            $xml->writeElement('dc:creator', $item->creator);
        }

        if ($item->description !== null && $item->description !== '') {
            $xml->writeElement('description', $item->description);
        }

        $xml->startElement('content:encoded');
        $xml->writeCdata(str_replace(']]>', ']]]]><![CDATA[>', $item->content));
        $xml->endElement();
        $xml->endElement();
    }

    protected function text(string $key): string
    {
        $value = $this->settings->get($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
