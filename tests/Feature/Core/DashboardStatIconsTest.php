<?php

use App\Models\User;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardStat;
use Inertia\Testing\AssertableInertia as Assert;

// Icon keys of the Verso icon set, read from the JS index file.
function versoIconKeys(): array
{
    $source = (string) file_get_contents(resource_path('js/components/verso/icons/index.tsx'));
    preg_match('/export const versoIcons = \{(.*?)\} satisfies/s', $source, $block);
    preg_match_all('/^\s*([a-z]+):/m', $block[1] ?? '', $keys);

    return $keys[1];
}

it('gives every built-in stat an icon from the Verso icon set', function () {
    $admin = User::factory()->admin()->create(['name' => 'Lucia Ferrer']);
    $stats = app(Dashboard::class)->statsFor($admin);
    $keys = versoIconKeys();

    expect($stats)->not->toBeEmpty()
        ->and($keys)->toContain('posts', 'comments', 'media');

    foreach ($stats as $stat) {
        expect($stat['icon'])->toBeString("The {$stat['key']} stat has no icon.")
            ->and($keys)->toContain($stat['icon']);
    }
});

it('uses fitting icons for each kind of stat', function () {
    $admin = User::factory()->admin()->create(['name' => 'Lucia Ferrer']);
    $icons = collect(app(Dashboard::class)->statsFor($admin))->pluck('icon', 'key');

    expect($icons->all())->toMatchArray([
        'published.post' => 'posts',
        'published.page' => 'pages',
        'drafts' => 'entries',
        'scheduled' => 'calendar',
        'comments.pending' => 'comments',
        'media' => 'media',
        'users' => 'users',
        'invitations' => 'users',
    ]);
});

it('sends the icons to the dashboard page', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Lucia Ferrer']))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats', fn ($stats) => collect($stats)->firstWhere('key', 'comments.pending')['icon'] === 'comments')
        );
});

it('keeps the icon optional for stats added by plugins', function () {
    $stat = new DashboardStat(key: 'newsletter.subscribers', label: 'Newsletter subscribers', count: fn (): int => 318);

    expect($stat->icon)->toBeNull()
        ->and($stat->toArray()['icon'])->toBeNull();
});
