<?php

use Verso\Plugins\Newsletter\Models\Campaign;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Newsletter\Models\Subscriber;

// A confirmed subscriber.
function subscribedReader(string $email = 'ines.cardoso@example.pt', ?string $name = 'Ines Cardoso'): Subscriber
{
    return Subscriber::query()->create(['email' => $email, 'name' => $name, 'status' => 'subscribed', 'confirmed_at' => now()]);
}

function weeklyDigest(): MailingList
{
    return MailingList::query()->create(['name' => 'Weekly digest', 'description' => 'The best stories of the week.']);
}

// A draft campaign with a heading, a paragraph with a link and a button.
function autumnIssue(array $attributes = []): Campaign
{
    return Campaign::query()->create([
        'subject' => 'Autumn in the Douro valley',
        'preheader' => 'Harvest stories and three new walks.',
        'blocks' => [
            ['id' => 'b_h', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Harvest season', 'children' => []],
            ['id' => 'b_p', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Read <a href="https://douro.example.pt/walks">our new walks</a> today.', 'children' => []],
        ],
        ...$attributes,
    ]);
}
