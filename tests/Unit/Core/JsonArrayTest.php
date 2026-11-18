<?php

use App\Models\User;
use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Database\JsonArray;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Facades\DB;

// MySQL gives JSON keys back sorted by length and then by name. This writes them that way on any database.
function storeWithMySqlKeyOrder(Entry $entry, string $column, string $json): void
{
    DB::table('entries')->where('id', $entry->id)->update([$column => $json]);
}

beforeEach(function () {
    $this->cast = new JsonArray;
    $this->model = new Entry;
});

it('decodes JSON into an array', function () {
    expect($this->cast->get($this->model, 'meta', '{"seo":{"title":"Harbour Nights"},"reading_time":4}', []))
        ->toBe(['seo' => ['title' => 'Harbour Nights'], 'reading_time' => 4])
        ->and($this->cast->get($this->model, 'meta', null, []))->toBeNull()
        ->and($this->cast->get($this->model, 'meta', '', []))->toBeNull();
});

it('encodes arrays like the array cast', function () {
    expect($this->cast->set($this->model, 'meta', ['layout' => 'wide', 'tags' => ['travel', 'food']], []))
        ->toBe('{"layout":"wide","tags":["travel","food"]}')
        ->and($this->cast->set($this->model, 'meta', null, []))->toBeNull();
});

it('treats the same data in another key order as equal', function () {
    expect($this->cast->compare(
        $this->model,
        'meta',
        '{"reading_time":4,"seo":{"title":"Harbour Nights","noindex":false}}',
        '{"seo":{"noindex":false,"title":"Harbour Nights"},"reading_time":4}',
    ))->toBeTrue();
});

it('sees a changed value or list order as a change', function () {
    expect($this->cast->compare($this->model, 'meta', '{"reading_time":4}', '{"reading_time":5}'))->toBeFalse()
        ->and($this->cast->compare($this->model, 'blocks', '[{"type":"paragraph"},{"type":"image"}]', '[{"type":"image"},{"type":"paragraph"}]'))->toBeFalse()
        ->and($this->cast->compare($this->model, 'meta', '{"reading_time":4}', null))->toBeFalse()
        ->and($this->cast->compare($this->model, 'meta', '{"reading_time":4}', '{"reading_time":"4"}'))->toBeFalse();
});

it('does not mark an entry dirty when only the key order differs', function () {
    $entry = Entry::factory()->create(['meta' => ['seo' => ['title' => 'Harbour Nights', 'description' => 'Fish markets after dark.']]]);
    storeWithMySqlKeyOrder($entry, 'meta', '{"seo":{"title":"Harbour Nights","description":"Fish markets after dark."}}');
    $entry->refresh();

    $entry->meta = ['seo' => ['title' => 'Harbour Nights', 'description' => 'Fish markets after dark.']];

    expect($entry->isDirty('meta'))->toBeFalse();

    $entry->meta = ['seo' => ['title' => 'Harbour Nights at Dawn', 'description' => 'Fish markets after dark.']];

    expect($entry->isDirty('meta'))->toBeTrue();
});

it('does not save a revision when the same content comes back in another key order', function () {
    $author = User::factory()->author()->create();
    $blocks = [['type' => 'paragraph', 'attrs' => ['text' => 'Grilled sardines with peppers.', 'align' => 'left']]];
    $entry = Entry::factory()->create(['blocks' => $blocks, 'meta' => ['reading_time' => 3, 'layout' => 'wide']]);
    storeWithMySqlKeyOrder($entry, 'blocks', '[{"type":"paragraph","attrs":{"text":"Grilled sardines with peppers.","align":"left"}}]');
    storeWithMySqlKeyOrder($entry, 'meta', '{"layout":"wide","reading_time":3}');

    app(UpdateEntry::class)->handle($entry->refresh(), ['blocks' => $blocks, 'meta' => ['reading_time' => 3, 'layout' => 'wide']], $author);

    expect($entry->revisions()->count())->toBe(0);
});

it('keeps media meta clean when only the key order differs', function () {
    $media = Media::factory()->create(['meta' => ['exif' => ['camera' => 'Fujifilm X100V', 'iso' => 400]]]);
    DB::table('media')->where('id', $media->id)->update(['meta' => '{"exif":{"iso":400,"camera":"Fujifilm X100V"}}']);
    $media->refresh();

    $media->meta = ['exif' => ['camera' => 'Fujifilm X100V', 'iso' => 400]];

    expect($media->isDirty('meta'))->toBeFalse();
});
