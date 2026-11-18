<?php

use App\Verso\Easy\Launch\LaunchCheck;
use App\Verso\Easy\Launch\LaunchChecklist;

function bakeryCheck(string $key, int $order, bool $done = false, ?Closure $applies = null): LaunchCheck
{
    return new LaunchCheck(
        key: $key,
        label: ucfirst(str_replace('-', ' ', $key)),
        description: 'A step for Harbor Lane Bakery.',
        href: fn (): string => '/admin/bakery/'.$key,
        done: fn (): bool => $done,
        order: $order,
        applies: $applies,
    );
}

it('registers the nine core steps in order', function () {
    expect(array_map(fn (LaunchCheck $check): string => $check->key, app(LaunchChecklist::class)->all()))
        ->toBe(['logo', 'colors', 'readable-colors', 'pages', 'contact', 'social', 'search', 'privacy', 'go-live']);
});

it('sorts steps, replaces a step with the same key and leaves out steps that do not apply', function () {
    $checklist = new LaunchChecklist;
    $checklist->register(bakeryCheck('opening-hours', 20));
    $checklist->register(bakeryCheck('menu-card', 10, done: true));
    $checklist->register(bakeryCheck('delivery-zone', 5, applies: fn (): bool => false));
    $checklist->register(bakeryCheck('opening-hours', 30, done: true));

    expect(array_map(fn (LaunchCheck $check): string => $check->key, $checklist->all()))->toBe(['menu-card', 'opening-hours'])
        ->and($checklist->has('delivery-zone'))->toBeTrue()
        ->and($checklist->isComplete())->toBeTrue();

    $checklist->unregister('opening-hours');

    expect($checklist->find('opening-hours'))->toBeNull();
});

it('gives the rows and the progress for the dashboard', function () {
    $checklist = new LaunchChecklist;
    $checklist->register(bakeryCheck('menu-card', 10, done: true));
    $checklist->register(new LaunchCheck('order-online', 'Take orders online', 'Turn on the shop.', '/admin/bakery/shop', fn (): bool => false, 20, 'Turn it on', 'post'));

    expect($checklist->toArray())->toBe([
        'items' => [
            ['key' => 'menu-card', 'label' => 'Menu card', 'description' => 'A step for Harbor Lane Bakery.', 'href' => '/admin/bakery/menu-card', 'action' => 'Do it', 'method' => 'get', 'done' => true],
            ['key' => 'order-online', 'label' => 'Take orders online', 'description' => 'Turn on the shop.', 'href' => '/admin/bakery/shop', 'action' => 'Turn it on', 'method' => 'post', 'done' => false],
        ],
        'done' => 1,
        'total' => 2,
    ])->and($checklist->isComplete())->toBeFalse();
});

it('refuses unknown steps, bad keys and unknown methods', function () {
    expect(fn () => (new LaunchChecklist)->get('logo'))->toThrow(InvalidArgumentException::class, 'Unknown launch check [logo].')
        ->and(fn () => bakeryCheck('Opening Hours', 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new LaunchCheck('menu', 'Menu', '', '/', fn (): bool => true, method: 'delete'))->toThrow(InvalidArgumentException::class);
});
