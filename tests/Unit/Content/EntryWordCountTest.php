<?php

use App\Verso\Content\Models\Entry;

it('counts words in blocks and their children, without tags', function () {
    $entry = new Entry(['blocks' => [
        ['type' => 'heading', 'content' => 'Olive harvest in Jaén'],
        ['type' => 'paragraph', 'content' => '<p>We picked <em>three</em> trees&nbsp;before lunch.</p>'],
        ['type' => 'columns', 'content' => '', 'children' => [
            ['type' => 'paragraph', 'content' => '<p>Don’t forget the nets!</p>'],
        ]],
        ['type' => 'image', 'attrs' => ['alt' => 'Nets under the trees']],
    ]]);

    expect($entry->wordCount())->toBe(14);
});

it('is zero for an empty entry', function () {
    expect((new Entry(['blocks' => []]))->wordCount())->toBe(0)
        ->and((new Entry)->wordCount())->toBe(0);
});
