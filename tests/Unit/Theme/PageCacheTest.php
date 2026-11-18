<?php

use App\Models\User;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->store = new Repository(new ArrayStore);
    $this->cache = new PageCache($this->store, enabled: true, ttl: 600, prefix: 'site.page', allowedQuery: ['page']);
});

it('accepts guest GET and HEAD requests', function () {
    expect($this->cache->accepts(Request::create('/blog')))->toBeTrue()
        ->and($this->cache->accepts(Request::create('/blog', 'HEAD')))->toBeTrue()
        ->and($this->cache->accepts(Request::create('/blog?page=3')))->toBeTrue();
});

it('refuses other requests', function () {
    $signedIn = Request::create('/blog');
    $signedIn->setUserResolver(fn () => new User(['name' => 'Rosa Vidal']));

    expect($this->cache->accepts(Request::create('/blog', 'POST')))->toBeFalse()
        ->and($this->cache->accepts(Request::create('/search?q=porto')))->toBeFalse()
        ->and($this->cache->accepts($signedIn))->toBeFalse();
});

it('refuses everything when it is off', function () {
    $off = new PageCache($this->store, enabled: false);
    $noTtl = new PageCache($this->store, enabled: true, ttl: 0);

    expect($off->accepts(Request::create('/')))->toBeFalse()
        ->and($off->enabled())->toBeFalse()
        ->and($noTtl->enabled())->toBeFalse();
});

it('stores only plain successful pages', function () {
    $private = new Response('Account page', 200, ['Cache-Control' => 'no-store, private']);

    expect($this->cache->shouldStore(new Response('Home', 200)))->toBeTrue()
        ->and($this->cache->shouldStore(new Response('Missing', 404)))->toBeFalse()
        ->and($this->cache->shouldStore(new Response('', 301)))->toBeFalse()
        ->and($this->cache->shouldStore($private))->toBeFalse();
});

it('saves and reads back a page with its content type', function () {
    $request = Request::create('https://journal.example.org/feed');
    $this->cache->put($request, new Response('<rss></rss>', 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']));

    $cached = $this->cache->get($request);

    expect($cached?->getContent())->toBe('<rss></rss>')
        ->and($cached?->headers->get('Content-Type'))->toBe('application/rss+xml; charset=UTF-8')
        ->and($cached?->getStatusCode())->toBe(200);
});

it('returns null for pages that are not cached', function () {
    expect($this->cache->get(Request::create('/about')))->toBeNull();
});

it('builds keys from host, path and allowed query', function () {
    $key = $this->cache->key(Request::create('https://journal.example.org/blog?page=2'));

    expect($key)->toStartWith('site.page:1:')
        ->and($key)->toBe($this->cache->key(Request::create('https://journal.example.org/blog/?page=2')))
        ->and($key)->not->toBe($this->cache->key(Request::create('https://journal.example.org/blog?page=3')))
        ->and($key)->not->toBe($this->cache->key(Request::create('https://shop.example.org/blog?page=2')));
});

it('forgets every page on flush', function () {
    $request = Request::create('/about');
    $this->cache->put($request, new Response('About us'));

    $this->cache->flush();

    expect($this->cache->version())->toBe(2)
        ->and($this->cache->get($request))->toBeNull();
});
