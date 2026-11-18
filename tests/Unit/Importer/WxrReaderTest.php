<?php

use App\Verso\Importer\Wxr\InvalidWxrFile;
use App\Verso\Importer\Wxr\WxrAuthor;
use App\Verso\Importer\Wxr\WxrItem;
use App\Verso\Importer\Wxr\WxrReader;
use App\Verso\Importer\Wxr\WxrTerm;

function fixtureReader(): WxrReader
{
    return new WxrReader(base_path('tests/Fixtures/wordpress-export.xml'));
}

function writeWxr(string $body, string $version = '1.2'): string
{
    $path = tempnam(sys_get_temp_dir(), 'wxr');
    file_put_contents($path, <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:excerpt="http://wordpress.org/export/{$version}/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/{$version}/">
        <channel><title>Quinta do Vale</title><wp:wxr_version>{$version}</wp:wxr_version>{$body}</channel></rss>
        XML);

    return $path;
}

/** @return list<WxrItem> */
function fixtureItems(): array
{
    return array_values(array_filter(iterator_to_array(fixtureReader()->records(), false), fn ($record) => $record instanceof WxrItem));
}

function fixtureItem(int $id): WxrItem
{
    return collect(fixtureItems())->firstOrFail(fn (WxrItem $item) => $item->id === $id);
}

it('reads the channel facts', function () {
    $channel = fixtureReader()->channel();

    expect($channel->title)->toBe('Harbourlight Journal')
        ->and($channel->baseSiteUrl)->toBe('https://harbourlight.example')
        ->and($channel->baseBlogUrl)->toBe('https://harbourlight.example')
        ->and($channel->wxrVersion)->toBe('1.2');
});

it('counts the items', function () {
    expect(fixtureReader()->countItems())->toBe(13);
});

it('reads authors', function () {
    $authors = array_values(array_filter(iterator_to_array(fixtureReader()->records(), false), fn ($record) => $record instanceof WxrAuthor));

    expect($authors)->toHaveCount(2)
        ->and($authors[0]->login)->toBe('maria.lopes')
        ->and($authors[0]->email)->toBe('maria.lopes@harbourlight.example')
        ->and($authors[0]->name())->toBe('Maria Lopes')
        ->and($authors[0]->id)->toBe(2)
        ->and($authors[1]->name())->toBe('Tom Reed');
});

it('reads categories, tags and other terms with decoded names', function () {
    $terms = array_values(array_filter(iterator_to_array(fixtureReader()->records(), false), fn ($record) => $record instanceof WxrTerm));

    expect($terms)->toHaveCount(6)
        ->and($terms[0])->toEqual(new WxrTerm('category', 'portugal', 'Portugal', 'travel'))
        ->and($terms[1]->description)->toBe('Trips by train, ferry and on foot.')
        ->and($terms[2]->name)->toBe('Food & Drink')
        ->and($terms[3]->taxonomy)->toBe('post_tag')
        ->and($terms[4]->description)->toBe('Cafés worth the detour.')
        ->and($terms[5]->taxonomy)->toBe('nav_menu');
});

it('reads a post with its fields', function () {
    $post = fixtureItem(10);

    expect($post->postType)->toBe('post')
        ->and($post->title)->toBe('A Weekend in Lisbon')
        ->and($post->slug)->toBe('a-weekend-in-lisbon')
        ->and($post->status)->toBe('publish')
        ->and($post->creator)->toBe('maria.lopes')
        ->and($post->link)->toBe('https://harbourlight.example/2024/03/18/a-weekend-in-lisbon/')
        ->and($post->dateGmt)->toBe('2024-03-18 09:30:00')
        ->and($post->excerpt)->toBe('Two slow days on the <em>seven hills</em> of Lisbon.')
        ->and($post->content)->toContain('<!-- wp:paragraph -->')
        ->and($post->isAttachment())->toBeFalse();
});

it('reads the terms, meta and comments of a post', function () {
    $post = fixtureItem(10);

    expect($post->terms)->toHaveCount(3)
        ->and($post->terms[0]->taxonomy)->toBe('category')
        ->and($post->terms[2]->name)->toBe('Azulejos')
        ->and($post->metaValue('_thumbnail_id'))->toBe('101')
        ->and($post->metaValue('_yoast_wpseo_focuskw'))->toBe('lisbon weekend')
        ->and($post->metaValue('missing'))->toBeNull()
        ->and($post->comments)->toHaveCount(4)
        ->and($post->comments[1]->parentId)->toBe(501)
        ->and($post->comments[1]->userId)->toBe(2)
        ->and($post->comments[0]->authorName)->toBe('Inês Carvalho');
});

it('keeps only approved and waiting comments', function () {
    $comments = fixtureItem(10)->comments;

    expect($comments[0]->isWanted())->toBeTrue()
        ->and($comments[0]->isApproved())->toBeTrue()
        ->and($comments[2]->isWanted())->toBeFalse()
        ->and($comments[3]->isWanted())->toBeFalse();
});

it('reads attachments', function () {
    $image = fixtureItem(101);

    expect($image->isAttachment())->toBeTrue()
        ->and($image->attachmentUrl)->toBe('https://harbourlight.example/wp-content/uploads/2024/03/tram-28-alfama.jpg')
        ->and($image->parentId)->toBe(10)
        ->and($image->metaValue('_wp_attachment_image_alt'))->toBe('Yellow tram 28 on a steep street in Alfama');
});

it('reads page parents and menu order', function () {
    $team = fixtureItem(21);

    expect($team->postType)->toBe('page')
        ->and($team->parentId)->toBe(20)
        ->and($team->menuOrder)->toBe(2);
});

it('reads older WXR versions too', function () {
    $path = writeWxr('<item><title>Old Mill</title><wp:post_id>7</wp:post_id><wp:post_type>post</wp:post_type><excerpt:encoded>A mill by the river.</excerpt:encoded></item>', '1.0');

    $items = iterator_to_array((new WxrReader($path))->records(), false);

    expect($items)->toHaveCount(1)
        ->and($items[0]->title)->toBe('Old Mill')
        ->and($items[0]->excerpt)->toBe('A mill by the river.');
});

it('decodes url encoded slugs', function () {
    $path = writeWxr('<item><title>Café</title><wp:post_id>8</wp:post_id><wp:post_name>caf%c3%a9-central</wp:post_name><wp:post_type>post</wp:post_type></item>');

    $items = iterator_to_array((new WxrReader($path))->records(), false);

    expect($items[0]->slug)->toBe('café-central');
});

it('fails when the file does not exist', function () {
    new WxrReader('/tmp/does-not-exist-'.uniqid().'.xml');
})->throws(InvalidWxrFile::class);

it('fails when the file is not a WordPress export', function () {
    $path = tempnam(sys_get_temp_dir(), 'rss');
    file_put_contents($path, '<?xml version="1.0"?><rss><channel><title>News</title><item><title>Hello</title></item></channel></rss>');

    (new WxrReader($path))->channel();
})->throws(InvalidWxrFile::class, 'not a WordPress export');

it('fails clearly on broken XML', function () {
    $path = writeWxr('<item><title>Broken</title><wp:post_id>9</wp:post_id>');

    iterator_to_array((new WxrReader($path))->records(), false);
})->throws(InvalidWxrFile::class, 'broken near line');
