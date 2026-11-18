<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Models\Redirect;
use App\Verso\Seo\Models\NotFoundHit;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function missing(string $path, int $hits, int $daysAgo = 0): NotFoundHit
{
    return NotFoundHit::query()->create([
        'path' => $path,
        'hits' => $hits,
        'referrer_host' => 'www.google.com',
        'first_seen_at' => Carbon::now()->subDays($daysAgo + 3),
        'last_seen_at' => Carbon::now()->subDays($daysAgo),
    ]);
}

it('lists missing addresses by hits with close matches', function () {
    Entry::factory()->published()->create(['title' => 'Riding Tram 28 in Lisbon', 'slug' => 'riding-tram-28']);
    Entry::factory()->published()->create(['title' => 'Pastel de nata', 'slug' => 'pastel-de-nata']);
    missing('/blog/riding-tram28', 14);
    missing('/old/contact.html', 3, 2);

    $this->actingAs($this->admin)
        ->get(route('admin.seo.not-found.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/seo/not-found')
            ->has('rows.data', 2)
            ->where('rows.data.0.path', '/blog/riding-tram28')
            ->where('rows.data.0.hits', 14)
            ->where('rows.data.0.referrer_host', 'www.google.com')
            ->where('rows.data.0.suggestions.0.path', '/blog/riding-tram-28')
            ->where('rows.data.0.suggestions.0.title', 'Riding Tram 28 in Lisbon')
            ->where('rows.data.1.suggestions', [])
            ->where('totals', ['paths' => 2, 'hits' => 17])
            ->where('filters', ['search' => '', 'sort' => 'hits'])
            ->where('can.manage', true));
});

it('searches and sorts the list', function () {
    missing('/menu-2024', 1, 0);
    missing('/catering', 9, 5);

    $this->actingAs($this->admin)
        ->get(route('admin.seo.not-found.index', ['sort' => 'recent']))
        ->assertInertia(fn (Assert $page) => $page->where('rows.data.0.path', '/menu-2024'));

    $this->actingAs($this->admin)
        ->get(route('admin.seo.not-found.index', ['search' => 'CATER']))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.path', '/catering'));
});

it('creates a redirect from a row with the existing redirects', function () {
    $hit = missing('/blog/riding-tram28', 14);

    $this->actingAs($this->admin)
        ->post(route('admin.seo.not-found.redirect', $hit), ['to_path' => '/blog/riding-tram-28', 'status_code' => 301])
        ->assertRedirect();

    expect(Redirect::findForPath('/blog/riding-tram28')?->to_path)->toBe('/blog/riding-tram-28')
        ->and(NotFoundHit::query()->count())->toBe(0);

    $this->get('/blog/riding-tram28')->assertRedirect('/blog/riding-tram-28');
});

it('validates the redirect target', function () {
    $hit = missing('/old-page', 2);

    $this->actingAs($this->admin)->post(route('admin.seo.not-found.redirect', $hit), ['to_path' => 'javascript:alert(1)'])->assertSessionHasErrors('to_path');
    $this->actingAs($this->admin)->post(route('admin.seo.not-found.redirect', $hit), ['to_path' => '/old-page'])->assertSessionHasErrors('to_path');
    $this->actingAs($this->admin)->post(route('admin.seo.not-found.redirect', $hit), ['to_path' => '/new-page', 'status_code' => 307])->assertSessionHasErrors('status_code');

    expect(Redirect::query()->count())->toBe(0);
});

it('removes one row or clears the list', function () {
    $one = missing('/a-typo', 1);
    missing('/another-typo', 1);

    $this->actingAs($this->admin)->delete(route('admin.seo.not-found.destroy', $one))->assertRedirect();
    expect(NotFoundHit::query()->count())->toBe(1);

    $this->actingAs($this->admin)->delete(route('admin.seo.not-found.clear'))->assertRedirect();
    expect(NotFoundHit::query()->count())->toBe(0);
});

it('keeps people without the capability out', function () {
    $hit = missing('/a-typo', 1);
    $author = User::factory()->author()->create();

    $this->actingAs($author)->get(route('admin.seo.not-found.index'))->assertForbidden();
    $this->actingAs($author)->post(route('admin.seo.not-found.redirect', $hit), ['to_path' => '/'])->assertForbidden();
    $this->actingAs($author)->delete(route('admin.seo.not-found.clear'))->assertForbidden();
});
