<?php

use App\Verso\Api\Http\Requests\EntryIndexRequest;

function entryIndexRequest(array $query): EntryIndexRequest
{
    return EntryIndexRequest::create('/api/v1/entries/post', 'GET', $query);
}

it('uses the per page default from config', function () {
    config(['verso.api.per_page' => 12]);

    expect(entryIndexRequest([])->perPage())->toBe(12)
        ->and(entryIndexRequest(['per_page' => '30'])->perPage())->toBe(30);
});

it('reads the sort column and direction', function () {
    expect(entryIndexRequest([])->sort())->toBe(['published_at', 'desc'])
        ->and(entryIndexRequest(['sort' => 'title'])->sort())->toBe(['title', 'asc'])
        ->and(entryIndexRequest(['sort' => '-menu_order'])->sort())->toBe(['menu_order', 'desc']);
});

it('trims the search text', function () {
    expect(entryIndexRequest(['search' => '  night trains '])->search())->toBe('night trains')
        ->and(entryIndexRequest([])->search())->toBe('');
});

it('limits per_page with the max from config', function () {
    config(['verso.api.max_per_page' => 40]);

    expect(entryIndexRequest([])->rules()['per_page'])->toContain('max:40');
});
