<?php

use App\Verso\Media\Enums\MediaType;
use App\Verso\Media\Models\Media;
use App\Verso\Media\Support\MediaLibraryQuery;
use Illuminate\Http\Request;

it('reads filters from the query string', function () {
    $query = new MediaLibraryQuery(10);

    expect($query->filters(Request::create('/admin/media', 'GET', ['search' => ' tram ', 'type' => 'video'])))
        ->toBe(['search' => 'tram', 'type' => MediaType::Video])
        ->and($query->filters(Request::create('/admin/media/library', 'GET', ['accept' => 'image']))['type'])
        ->toBe(MediaType::Image)
        ->and($query->filters(Request::create('/admin/media', 'GET', ['type' => 'spreadsheet', 'search' => ['x']])))
        ->toBe(['search' => '', 'type' => null]);
});

it('pages the newest files first', function () {
    $older = Media::factory()->create(['created_at' => now()->subDay()]);
    $newer = Media::factory()->create();
    Media::factory()->count(2)->create(['created_at' => now()->subWeek()]);

    $page = (new MediaLibraryQuery(2))->paginate(['search' => '', 'type' => null]);

    expect($page->total())->toBe(4)
        ->and($page->pluck('id')->all())->toBe([$newer->id, $older->id]);
});
