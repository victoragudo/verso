<?php

use App\Models\User;
use App\Verso\Easy\Help\HelpTopic;
use App\Verso\Easy\Help\HelpTopics;

it('needs a signed in person', function () {
    $this->getJson(route('admin.easy.help'))->assertUnauthorized();
});

it('gives the topics a person may see, with their steps and pages', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.easy.help'))
        ->assertOk()
        ->assertJsonCount(8, 'topics')
        ->assertJsonPath('topics.0.key', 'choose-starter')
        ->assertJsonPath('topics.4.illustration', 'menu')
        ->assertJsonPath('topics.4.routes', ['admin.site.menus.index'])
        ->assertJsonPath('topics.7.video', null);
});

it('leaves out topics an author cannot act on and adds plugin topics', function () {
    app(HelpTopics::class)->register(new HelpTopic('bread-orders', 'Take bread orders', 'Orders for tomorrow morning.', ['Open "Orders".', 'Press "Ready".'], order: 65));

    $keys = $this->actingAs(User::factory()->author()->create())->getJson(route('admin.easy.help'))->json('topics.*.key');

    expect($keys)->toBe(['edit-page', 'add-section', 'write-post', 'bread-orders', 'upload-photos']);
});

it('adds a video from the config', function () {
    config(['verso.easy.help.videos' => ['go-live' => 'https://videos.harborlane.example/go-live.mp4']]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.easy.help'))
        ->assertJsonPath('topics.7.video', 'https://videos.harborlane.example/go-live.mp4');
});
