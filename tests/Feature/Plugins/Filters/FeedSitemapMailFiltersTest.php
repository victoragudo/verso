<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\FeedItemsFilter;
use App\Verso\Plugins\Filters\OutgoingMailFilter;
use App\Verso\Plugins\Filters\SitemapEntriesFilter;
use App\Verso\Plugins\Mail\OutgoingMail;
use App\Verso\Site\Sitemap\SitemapUrl;
use App\Verso\Theme\Support\FeedItem;
use Illuminate\Mail\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);
    $this->author = User::factory()->author()->create(['name' => 'Rui Almeida']);
});

describe('SitemapEntriesFilter', function () {
    it('lets a plugin add a url to a type sitemap', function () {
        Entry::factory()->published()->by($this->author)->create(['title' => 'Cork Forests of Alentejo', 'slug' => 'cork-forests-of-alentejo']);

        Filters::add(SitemapEntriesFilter::class, fn (array $urls, string $sitemap, int $page): array => $sitemap === 'post'
            ? [...$urls, new SitemapUrl(url('/events/cork-festival'), '2026-11-01T10:00:00+00:00')]
            : $urls);

        $this->get('/sitemap-post.xml')->assertOk()
            ->assertSee('<loc>'.url('/blog/cork-forests-of-alentejo').'</loc>', false)
            ->assertSee('<loc>'.url('/events/cork-festival').'</loc>', false)
            ->assertSee('<lastmod>2026-11-01T10:00:00+00:00</lastmod>', false);
    });

    it('lets a plugin remove urls', function () {
        Entry::factory()->published()->by($this->author)->create(['title' => 'Members Only Itinerary', 'slug' => 'members-only-itinerary']);

        Filters::add(SitemapEntriesFilter::class, fn (array $urls): array => array_values(array_filter(
            $urls,
            fn (SitemapUrl $url): bool => ! str_contains($url->location, 'members-only'),
        )));

        $this->get('/sitemap-post.xml')->assertOk()->assertDontSee('members-only-itinerary');
    });
});

describe('FeedItemsFilter', function () {
    it('lets a plugin add and change feed items', function () {
        Entry::factory()->published()->by($this->author)->create(['title' => 'Night Train to Faro', 'published_at' => now()->subDay()]);

        Filters::add(FeedItemsFilter::class, function (array $items, string $feedUrl): array {
            $items = array_map(fn (FeedItem $item): FeedItem => new FeedItem(
                title: '[Travel] '.$item->title,
                link: $item->link,
                publishedAt: $item->publishedAt,
                content: $item->content,
                description: $item->description,
                creator: $item->creator,
                entry: $item->entry,
            ), $items);

            return [...$items, new FeedItem('Podcast: Walking the Rota Vicentina', url('/podcast/rota-vicentina'), Carbon::parse('2026-11-10 08:00:00'), '<p>Episode 12.</p>')];
        });

        $this->get('/feed')->assertOk()
            ->assertSee('<title>[Travel] Night Train to Faro</title>', false)
            ->assertSee('<title>Podcast: Walking the Rota Vicentina</title>', false)
            ->assertSee('<dc:creator>Rui Almeida</dc:creator>', false)
            ->assertSee(url('/podcast/rota-vicentina'), false);
    });
});

describe('OutgoingMailFilter', function () {
    it('lets a plugin change a mail before it is sent', function () {
        Filters::add(OutgoingMailFilter::class, function (OutgoingMail $mail): OutgoingMail {
            $mail->message->subject('[Verso] '.$mail->subject());
            $mail->message->getHeaders()->addTextHeader('X-Site', 'Lisbon Notes');

            return $mail;
        });

        Mail::raw('Your comment was approved.', fn (Message $message) => $message->to('marta@example.org')->subject('Comment approved'));

        $sent = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();

        expect($sent->getSubject())->toBe('[Verso] Comment approved')
            ->and($sent->getHeaders()->get('X-Site')?->getBodyAsString())->toBe('Lisbon Notes');
    });

    it('lets a plugin cancel a mail', function () {
        Filters::add(OutgoingMailFilter::class, fn (OutgoingMail $mail): OutgoingMail => in_array('blocked@example.org', $mail->recipients(), true)
            ? $mail->cancel()
            : $mail);

        Mail::raw('Weekly digest.', fn (Message $message) => $message->to('blocked@example.org')->subject('Digest'));
        Mail::raw('Weekly digest.', fn (Message $message) => $message->to('joana@example.org')->subject('Digest'));

        $recipients = app('mailer')->getSymfonyTransport()->messages()
            ->map(fn ($message): string => $message->getOriginalMessage()->getTo()[0]->getAddress())
            ->all();

        expect($recipients)->toBe(['joana@example.org']);
    });
});
