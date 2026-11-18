<?php

use App\Models\User;
use App\Verso\Content\Enums\EntryAbility;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Media\Actions\UploadMedia;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\AdminNavFilter;
use App\Verso\Plugins\Filters\EntryCapabilityFilter;
use App\Verso\Plugins\Filters\UploadRulesFilter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

describe('AdminNavFilter', function () {
    it('lets a plugin hide and rename sidebar items for one user', function () {
        $editor = User::factory()->editor()->create();

        Filters::add(AdminNavFilter::class, fn (array $items, ?User $user): array => array_values(array_map(
            fn (NavItem $item): NavItem => $item->key === 'media'
                ? new NavItem($item->key, 'Photo library', $item->route, $item->icon, $item->order, $item->capability, $item->parameters, $item->group)
                : $item,
            array_filter($items, fn (NavItem $item): bool => $item->key !== 'comments'),
        )));

        $nav = collect(AdminNav::forUser($editor));

        expect($nav->pluck('key'))->not->toContain('comments')
            ->and($nav->firstWhere('key', 'media')['label'])->toBe('Photo library');
    });

    it('only receives the items the user may see', function () {
        $author = User::factory()->author()->create();
        $keys = [];

        Filters::add(AdminNavFilter::class, function (array $items) use (&$keys): array {
            $keys = array_map(fn (NavItem $item): string => $item->key, $items);

            return $items;
        });

        AdminNav::forUser($author);

        expect($keys)->not->toContain('plugins')->not->toContain('users');
    });
});

describe('EntryCapabilityFilter', function () {
    it('lets a plugin stop authors from deleting published entries', function () {
        $author = User::factory()->author()->create();
        $entry = Entry::factory()->published()->by($author)->create(['title' => 'Surfing in Ericeira']);
        $draft = Entry::factory()->by($author)->create(['title' => 'Draft Notes from Peniche']);

        expect($author->can('delete', $entry))->toBeTrue();

        Filters::add(EntryCapabilityFilter::class, fn (bool $allowed, User $user, EntryAbility $ability, ?Entry $entry): bool => $ability === EntryAbility::Delete && $entry?->isPublished()
            ? false
            : $allowed);

        expect($author->can('delete', $entry))->toBeFalse()
            ->and($author->can('delete', $draft))->toBeTrue()
            ->and($author->can('update', $draft))->toBeTrue();
    });

    it('lets a plugin give contributors the right to publish', function () {
        $contributor = User::factory()->contributor()->create();

        expect($contributor->can('publish', Entry::class))->toBeFalse();

        Filters::add(EntryCapabilityFilter::class, fn (bool $allowed, User $user, EntryAbility $ability): bool => $ability === EntryAbility::Publish ? true : $allowed);

        expect($contributor->can('publish', Entry::class))->toBeTrue();
    });

    it('blocks the admin edit page when a plugin says no', function () {
        $editor = User::factory()->editor()->create();
        $entry = Entry::factory()->create(['title' => 'Locked Press Release']);

        Filters::add(EntryCapabilityFilter::class, fn (bool $allowed, User $user, EntryAbility $ability, ?Entry $entry): bool => $entry?->title === 'Locked Press Release' ? false : $allowed);

        $this->actingAs($editor)->get(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $entry->id]))->assertForbidden();
    });
});

describe('UploadRulesFilter', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('lets a plugin add a stricter rule', function () {
        $author = User::factory()->author()->create();

        Filters::add(UploadRulesFilter::class, fn (array $rules, UploadedFile $file, ?User $uploader): array => [...$rules, 'dimensions:max_width=1200']);

        expect(fn () => app(UploadMedia::class)->handle(UploadedFile::fake()->image('Wide Beach at Nazaré.jpg', 2400, 1200), $author))
            ->toThrow(ValidationException::class);

        $media = app(UploadMedia::class)->handle(UploadedFile::fake()->image('Small Beach at Nazaré.jpg', 800, 600), $author);

        expect($media->width)->toBe(800);
    });

    it('passes the uploader to the callbacks', function () {
        $author = User::factory()->author()->create(['name' => 'Beatriz Lopes']);
        $names = [];

        Filters::add(UploadRulesFilter::class, function (array $rules, UploadedFile $file, ?User $uploader) use (&$names): array {
            $names[] = $uploader?->name;

            return $rules;
        });

        app(UploadMedia::class)->handle(UploadedFile::fake()->image('Market in Braga.jpg', 640, 480), $author);

        expect($names)->toBe(['Beatriz Lopes']);
    });
});
