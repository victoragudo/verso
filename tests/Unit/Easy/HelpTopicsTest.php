<?php

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use App\Verso\Easy\Help\HelpTopic;
use App\Verso\Easy\Help\HelpTopics;

it('registers the core topics in order', function () {
    expect(array_map(fn (HelpTopic $topic): string => $topic->key, app(HelpTopics::class)->all()))->toBe([
        'choose-starter', 'make-it-yours', 'edit-page', 'add-section', 'change-menu', 'write-post', 'upload-photos', 'go-live',
    ]);

    foreach (app(HelpTopics::class)->all() as $topic) {
        expect($topic->steps)->not->toBeEmpty()
            ->and($topic->routes)->not->toBeEmpty()
            ->and(implode(' ', [$topic->title, $topic->summary, ...$topic->steps]))->not->toContain("\u{2014}", 'taxonomy', 'slug', 'entries');
    }
});

it('only gives people the topics they can act on', function () {
    $topics = app(HelpTopics::class);
    $author = User::factory()->author()->create();
    $keys = array_column($topics->forUser($author), 'key');

    expect($keys)->toContain('edit-page', 'write-post', 'upload-photos')
        ->not->toContain('choose-starter', 'make-it-yours', 'go-live')
        ->and($topics->forUser(null))->toBe([]);
});

it('lets a plugin add and replace topics, with a video from the config', function () {
    config(['verso.easy.help.videos' => ['bake-orders' => 'https://videos.harborlane.example/orders.mp4']]);
    $topics = new HelpTopics;
    $topics->register(new HelpTopic('bake-orders', 'Take bread orders', 'Let people order bread for tomorrow.', ['Open "Orders".'], 'orders', ['admin.bakery.orders'], order: 20, video: 'https://old.example/orders.mp4'));
    $topics->register(new HelpTopic('opening-hours', 'Change your opening hours', 'Show when the shop is open.', ['Open "Your site details".'], capability: Capability::ManageSettings, order: 10));

    expect(array_column($topics->forUser(User::factory()->admin()->create()), 'key'))->toBe(['opening-hours', 'bake-orders'])
        ->and($topics->get('bake-orders')->toArray())->toBe([
            'key' => 'bake-orders',
            'title' => 'Take bread orders',
            'summary' => 'Let people order bread for tomorrow.',
            'steps' => ['Open "Orders".'],
            'illustration' => 'orders',
            'routes' => ['admin.bakery.orders'],
            'keywords' => [],
            'video' => 'https://videos.harborlane.example/orders.mp4',
        ])
        ->and($topics->get('opening-hours')->toArray()['video'])->toBeNull();

    $topics->unregister('bake-orders');

    expect($topics->has('bake-orders'))->toBeFalse()
        ->and(fn () => $topics->get('bake-orders'))->toThrow(InvalidArgumentException::class);
});

it('refuses bad keys and topics without steps', function () {
    expect(fn () => new HelpTopic('Bake Orders', 'Orders', '', ['Open it.']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new HelpTopic('orders', 'Orders', '', []))->toThrow(InvalidArgumentException::class);
});
