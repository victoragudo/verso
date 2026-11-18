<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->author = User::factory()->author()->create(['name' => 'Clara Moreno']);
});

it('shows the public profile form with its limits', function () {
    config(['verso.users.profile.bio_max' => 600, 'verso.users.profile.social_links_max' => 5]);
    $this->author->update(['bio' => 'Cooks, bakes bread on Sundays.', 'social_links' => ['https://bsky.app/profile/clara.example']]);

    $this->actingAs($this->author)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('publicProfile.bio', 'Cooks, bakes bread on Sundays.')
            ->where('publicProfile.socialLinks', ['https://bsky.app/profile/clara.example'])
            ->where('publicProfile.avatar', null)
            ->where('publicProfile.canUploadAvatar', true)
            ->where('publicProfile.authorUrl', null)
            ->where('publicProfile.limits.bioMax', 600)
            ->where('publicProfile.limits.socialLinksMax', 5));
});

it('links to the author page once the person has published posts', function () {
    Entry::factory()->published()->by($this->author)->create(['title' => 'Sourdough in a small kitchen']);

    $this->actingAs($this->author)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('publicProfile.authorUrl', url('/author/clara-moreno')));
});

it('saves the bio and clean, unique social links', function () {
    $this->actingAs($this->author)
        ->put(route('profile.public.update'), [
            'bio' => '  Grows far too many tomatoes on a balcony in Lisbon.  ',
            'social_links' => ['https://bsky.app/profile/clara.example', '', 'https://bsky.app/profile/clara.example', 'https://www.instagram.com/clara.cooks'],
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    $user = $this->author->fresh();

    expect($user->bio)->toBe('Grows far too many tomatoes on a balcony in Lisbon.')
        ->and($user->social_links)->toBe(['https://bsky.app/profile/clara.example', 'https://www.instagram.com/clara.cooks'])
        ->and($user->socialProfiles())->toBe([
            ['url' => 'https://bsky.app/profile/clara.example', 'label' => 'Bluesky'],
            ['url' => 'https://www.instagram.com/clara.cooks', 'label' => 'Instagram'],
        ]);

    $this->actingAs($this->author)->put(route('profile.public.update'), ['bio' => '', 'social_links' => []])->assertSessionHasNoErrors();

    expect($this->author->fresh()->bio)->toBeNull()->and($this->author->fresh()->social_links)->toBeNull();
});

it('refuses unsafe links, too many links and a long bio', function () {
    config(['verso.users.profile.bio_max' => 50, 'verso.users.profile.social_links_max' => 2]);

    $this->actingAs($this->author)
        ->put(route('profile.public.update'), ['bio' => 'Short.', 'social_links' => ['javascript:alert(1)']])
        ->assertSessionHasErrors('social_links.0');

    $this->actingAs($this->author)
        ->put(route('profile.public.update'), ['bio' => 'Short.', 'social_links' => ['https://a.example', 'https://b.example', 'https://c.example']])
        ->assertSessionHasErrors('social_links');

    $this->actingAs($this->author)
        ->put(route('profile.public.update'), ['bio' => str_repeat('Bread ', 20), 'social_links' => []])
        ->assertSessionHasErrors('bio');
});

it('uploads a profile photo to the media library and removes it', function () {
    $this->actingAs($this->author)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('clara-portrait.jpg', 400, 400)])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    $media = Media::query()->sole();

    expect($this->author->fresh()->avatar_media_id)->toBe($media->id)
        ->and($media->uploaded_by)->toBe($this->author->id)
        ->and($media->alt)->toBe('Clara Moreno');

    $this->actingAs($this->author)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('publicProfile.avatar.id', $media->id)->where('publicProfile.avatar.url', fn (string $url) => str_contains($url, '/img/')));

    $this->actingAs($this->author)->delete(route('profile.avatar.destroy'))->assertRedirect(route('profile.edit'));

    expect($this->author->fresh()->avatar_media_id)->toBeNull()
        ->and(Media::query()->count())->toBe(1);
});

it('checks the photo type, size and the upload right', function () {
    config(['verso.users.profile.avatar_max_kb' => 20]);

    $this->actingAs($this->author)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('avatar');

    $this->actingAs($this->author)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('huge.jpg')->size(40)])
        ->assertSessionHasErrors('avatar');

    $subscriber = User::factory()->create(['name' => 'Tomas Berg']);

    $this->actingAs($subscriber)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('tomas.jpg', 200, 200)])
        ->assertForbidden();
});

it('gives every user a stable, unique slug', function () {
    $twin = User::factory()->create(['name' => 'Clara Moreno']);
    $nameless = User::factory()->create(['name' => '!!!']);

    expect($this->author->slug)->toBe('clara-moreno')
        ->and($twin->slug)->toBe('clara-moreno-2')
        ->and($nameless->slug)->toBe('author');

    $this->author->update(['name' => 'Clara Moreno Silva']);

    expect($this->author->fresh()->slug)->toBe('clara-moreno');
});
