<?php

use App\Models\User;
use App\Verso\Core\Models\Redirect;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('lists redirects with their hits', function () {
    $old = Redirect::factory()->create(['from_path' => '/2019/summer-in-porto', 'to_path' => '/blog/summer-in-porto']);
    $old->forceFill(['hits' => 41])->save();
    Redirect::factory()->temporary()->create(['from_path' => '/shop', 'to_path' => 'https://market.example.org']);

    $this->actingAs($this->admin)
        ->get(route('admin.site.redirects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site/redirects')
            ->has('redirects.data', 2)
            ->where('redirects.data.0.from_path', '/shop')
            ->where('redirects.data.0.status_code', 302)
            ->where('redirects.data.1.hits', 41)
            ->where('totalHits', 41)
            ->where('statusCodes', [301, 302])
            ->where('defaultStatus', 301)
            ->where('filters', ['search' => '', 'sort' => 'recent'])
        );
});

it('sorts redirects by hits', function () {
    Redirect::factory()->create(['from_path' => '/quiet'])->forceFill(['hits' => 2])->save();
    Redirect::factory()->create(['from_path' => '/popular'])->forceFill(['hits' => 300])->save();

    $this->actingAs($this->admin)
        ->get(route('admin.site.redirects.index', ['sort' => 'hits']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('redirects.data.0.from_path', '/popular')
            ->where('filters.sort', 'hits'));
});

it('falls back to the default sort for unknown values', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.site.redirects.index', ['sort' => 'random']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'recent'));
});

it('searches both paths', function () {
    Redirect::factory()->create(['from_path' => '/recipes/paella', 'to_path' => '/blog/paella']);
    Redirect::factory()->create(['from_path' => '/old-contact', 'to_path' => '/contact']);

    $this->actingAs($this->admin)
        ->get(route('admin.site.redirects.index', ['search' => 'paella']))
        ->assertInertia(fn (Assert $page) => $page->has('redirects.data', 1)->where('redirects.data.0.to_path', '/blog/paella'));

    $this->actingAs($this->admin)
        ->get(route('admin.site.redirects.index', ['search' => '/contact']))
        ->assertInertia(fn (Assert $page) => $page->has('redirects.data', 1));

    $this->actingAs($this->admin)
        ->get(route('admin.site.redirects.index', ['search' => 'Paella']))
        ->assertInertia(fn (Assert $page) => $page->has('redirects.data', 1));
});

it('creates a redirect', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.site.redirects.store'), ['from_path' => 'old-about/', 'to_path' => '/about', 'status_code' => 302])
        ->assertRedirect(route('admin.site.redirects.index'));

    $redirect = Redirect::firstWhere('from_path', '/old-about');
    expect($redirect->to_path)->toBe('/about')
        ->and($redirect->status_code)->toBe(302)
        ->and($redirect->hits)->toBe(0);
});

it('accepts external targets', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.site.redirects.store'), ['from_path' => '/newsletter', 'to_path' => 'https://letters.example.org/signup', 'status_code' => 301])
        ->assertSessionHasNoErrors();

    expect(Redirect::findForPath('/newsletter')->to_path)->toBe('https://letters.example.org/signup');
});

it('avoids redirect chains', function () {
    Redirect::point('/2018/garden', '/garden-notes');

    $this->actingAs($this->admin)
        ->post(route('admin.site.redirects.store'), ['from_path' => '/garden-notes', 'to_path' => '/blog/why-our-garden-loves-tomatoes', 'status_code' => 301]);

    expect(Redirect::findForPath('/2018/garden')->to_path)->toBe('/blog/why-our-garden-loves-tomatoes');
});

it('validates the redirect form', function (array $data, string $field) {
    $this->actingAs($this->admin)
        ->post(route('admin.site.redirects.store'), [...['from_path' => '/old', 'to_path' => '/new', 'status_code' => 301], ...$data])
        ->assertSessionHasErrors($field);

    expect(Redirect::count())->toBe(0);
})->with([
    'missing from' => [['from_path' => ''], 'from_path'],
    'full url as from' => [['from_path' => 'https://example.org/old'], 'from_path'],
    'missing target' => [['to_path' => ''], 'to_path'],
    'javascript target' => [['to_path' => 'javascript:alert(1)'], 'to_path'],
    'relative target' => [['to_path' => 'new-page'], 'to_path'],
    'bad status' => [['status_code' => 307], 'status_code'],
    'loop' => [['from_path' => '/same-page', 'to_path' => '/same-page/'], 'to_path'],
]);

it('deletes a redirect', function () {
    $redirect = Redirect::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.site.redirects.destroy', $redirect))
        ->assertRedirect();

    expect(Redirect::count())->toBe(0);
});

it('forbids users without the settings capability', function () {
    $editor = User::factory()->editor()->create();
    $redirect = Redirect::factory()->create();

    $this->actingAs($editor)->get(route('admin.site.redirects.index'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.site.redirects.store'), ['from_path' => '/a', 'to_path' => '/b', 'status_code' => 301])->assertForbidden();
    $this->actingAs($editor)->delete(route('admin.site.redirects.destroy', $redirect))->assertForbidden();

    expect(Redirect::count())->toBe(1);
});
