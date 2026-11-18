<?php

use App\Models\User;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('adds the media library to the admin nav for authors', function () {
    $this->actingAs(User::factory()->author()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('versoNav', fn ($nav) => collect(collect($nav)->firstWhere('key', 'media'))->only(['key', 'label', 'href', 'icon', 'group', 'parent'])->all() === [
                'key' => 'media',
                'label' => 'Media',
                'href' => '/admin/media',
                'icon' => 'media',
                'group' => 'content',
                'parent' => null,
            ])
        );
});

it('hides the media library from contributors', function () {
    $this->actingAs(User::factory()->contributor()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('versoNav', fn ($nav) => collect($nav)->firstWhere('key', 'media') === null)
        );
});

it('shows the media count on the dashboard', function () {
    Storage::fake('public');
    Media::factory()->count(3)->create();

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats', fn ($stats) => collect($stats)->firstWhere('key', 'media') === [
                'key' => 'media',
                'label' => 'Media files',
                'value' => 3,
                'href' => '/admin/media',
                'icon' => 'media',
            ])
        );
});
