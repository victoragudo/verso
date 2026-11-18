<?php

use App\Models\User;
use App\Verso\Core\Database\TextSearch;
use App\Verso\Core\Models\Redirect;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    User::factory()->create(['name' => 'Lucía Fernández', 'email' => 'lucia@harbourlight.example']);
    User::factory()->create(['name' => 'Marco Rossi', 'email' => 'marco.rossi@harbourlight.example']);
    User::factory()->create(['name' => 'Ingrid Nyberg', 'email' => 'ingrid_100%@nordic.example']);
});

function searchUserNames(string $term): array
{
    return TextSearch::apply(User::query(), ['name', 'email'], $term)
        ->orderBy('name')
        ->pluck('name')
        ->all();
}

it('ignores the case of the search text', function (string $term) {
    expect(searchUserNames($term))->toBe(['Marco Rossi']);
})->with(['rossi', 'ROSSI', 'RoSsI', 'marco']);

it('matches any of the given columns', function () {
    expect(searchUserNames('harbourlight'))->toBe(['Lucía Fernández', 'Marco Rossi'])
        ->and(searchUserNames('Nyberg'))->toBe(['Ingrid Nyberg']);
});

it('treats percent and underscore as plain text', function () {
    expect(searchUserNames('100%'))->toBe(['Ingrid Nyberg'])
        ->and(searchUserNames('_'))->toBe(['Ingrid Nyberg'])
        ->and(searchUserNames('%'))->toBe(['Ingrid Nyberg'])
        ->and(searchUserNames('o_r'))->toBe([]);
});

it('treats the escape character as plain text', function () {
    User::factory()->create(['name' => 'Wow! Studio', 'email' => 'hello@wow-studio.example']);

    expect(searchUserNames('wow!'))->toBe(['Wow! Studio'])
        ->and(searchUserNames('!'))->toBe(['Wow! Studio']);
});

it('leaves the query alone for an empty search', function () {
    expect(searchUserNames(''))->toHaveCount(3);
});

it('keeps other conditions outside the search group', function () {
    $names = TextSearch::apply(User::query()->where('name', '!=', 'Marco Rossi'), ['name', 'email'], 'harbourlight')
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Lucía Fernández']);
});

it('works with the plain query builder', function () {
    Redirect::point('/Recipes/Paella', '/blog/valencian-paella');
    Redirect::point('/shop', '/store');

    $paths = TextSearch::apply(DB::table('redirects'), ['from_path', 'to_path'], 'PAELLA')->pluck('from_path')->all();

    expect($paths)->toBe(['/Recipes/Paella']);
});

it('builds an escaped contains pattern', function () {
    expect(TextSearch::pattern('50% off_now!'))->toBe('%50!% off!_now!!%');
});
