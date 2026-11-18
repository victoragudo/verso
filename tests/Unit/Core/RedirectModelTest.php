<?php

use App\Verso\Core\Models\Redirect;

it('normalizes paths', function (string $input, string $expected) {
    expect(Redirect::normalizePath($input))->toBe($expected);
})->with([
    ['old-post', '/old-post'],
    ['/2019/05/summer-in-porto/', '/2019/05/summer-in-porto'],
    ['https://example.org/about-us/?ref=menu', '/about-us'],
    ['/', '/'],
    ['/caf%C3%A9-con-leche', '/café-con-leche'],
]);

it('keeps full URLs as targets', function () {
    expect(Redirect::normalizeTarget('https://archive.example.org/porto'))->toBe('https://archive.example.org/porto')
        ->and(Redirect::normalizeTarget('blog/porto/'))->toBe('/blog/porto');
});

it('creates a permanent redirect by default', function () {
    $redirect = Redirect::point('/2019/05/summer-in-porto/', '/blog/summer-in-porto');

    expect($redirect->from_path)->toBe('/2019/05/summer-in-porto')
        ->and($redirect->to_path)->toBe('/blog/summer-in-porto')
        ->and($redirect->status_code)->toBe(301)
        ->and($redirect->isPermanent())->toBeTrue();
});

it('uses the default status from config', function () {
    config(['verso.redirects.default_status' => 302]);

    expect(Redirect::point('/shop', '/store')->isPermanent())->toBeFalse();
});

it('updates the target of an existing redirect', function () {
    Redirect::point('/recipes/paella', '/blog/paella');
    Redirect::point('/recipes/paella', '/blog/valencian-paella', 302);

    expect(Redirect::count())->toBe(1)
        ->and(Redirect::findForPath('/recipes/paella')->to_path)->toBe('/blog/valencian-paella');
});

it('avoids chains when a target moves again', function () {
    Redirect::point('/a-weekend-in-lisbon', '/lisbon-weekend');
    Redirect::point('/lisbon-weekend', '/blog/lisbon-weekend');

    expect(Redirect::findForPath('/a-weekend-in-lisbon')->to_path)->toBe('/blog/lisbon-weekend');
});

it('removes a redirect that would loop back', function () {
    Redirect::point('/about', '/about-us');
    Redirect::point('/about-us', '/about');

    expect(Redirect::findForPath('/about'))->toBeNull()
        ->and(Redirect::findForPath('/about-us')->to_path)->toBe('/about');
});

it('rejects redirects to the same path', function () {
    Redirect::point('/contact/', 'contact');
})->throws(InvalidArgumentException::class);

it('rejects unknown status codes', function () {
    Redirect::point('/contact', '/contact-us', 307);
})->throws(InvalidArgumentException::class);

it('counts hits', function () {
    $redirect = Redirect::factory()->create();

    $redirect->recordHit();
    $redirect->recordHit();

    expect($redirect->hits)->toBe(2)
        ->and($redirect->fresh()->hits)->toBe(2);
});

it('matches paths with their exact case on every database', function () {
    Redirect::point('/About-Us', '/about-us');
    Redirect::point('/Shop', '/store');
    Redirect::point('/shop', '/market');

    expect(Redirect::findForPath('/about-us'))->toBeNull()
        ->and(Redirect::findForPath('/About-Us')->to_path)->toBe('/about-us')
        ->and(Redirect::findForPath('/Shop')->to_path)->toBe('/store')
        ->and(Redirect::findForPath('/shop')->to_path)->toBe('/market')
        ->and(Redirect::count())->toBe(3);
});
