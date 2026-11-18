<?php

use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\Http\Middleware\CachePage;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->cache = new PageCache(new Repository(new ArrayStore));
    $this->middleware = new CachePage($this->cache);
    $this->calls = 0;
    $this->next = function () {
        $this->calls++;

        return new Response('Fresh bread every morning');
    };
});

it('runs the page once and then serves it from the cache', function () {
    $first = $this->middleware->handle(Request::create('/bakery'), $this->next);
    $second = $this->middleware->handle(Request::create('/bakery'), $this->next);

    expect($first->headers->get(PageCache::HEADER))->toBe('MISS')
        ->and($second->headers->get(PageCache::HEADER))->toBe('HIT')
        ->and($second->getContent())->toBe('Fresh bread every morning')
        ->and($this->calls)->toBe(1);
});

it('passes requests it cannot cache straight through', function () {
    $response = $this->middleware->handle(Request::create('/bakery', 'POST'), $this->next);

    expect($response->headers->has(PageCache::HEADER))->toBeFalse()
        ->and($this->calls)->toBe(1);
});

it('does not save error pages', function () {
    $missing = fn () => new Response('Not found', 404);

    $this->middleware->handle(Request::create('/bakery'), $missing);

    expect($this->cache->get(Request::create('/bakery')))->toBeNull();
});
