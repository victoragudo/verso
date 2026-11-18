<?php

use App\Models\User;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\WordPress\AttachmentImporter;
use App\Verso\Importer\WordPress\AuthorImporter;
use App\Verso\Importer\WordPress\EntryImporter;
use App\Verso\Importer\WordPress\ImportedEntry;
use App\Verso\Importer\WordPress\ImportState;
use App\Verso\Importer\WordPress\LegacyQueryPath;
use App\Verso\Importer\WordPress\RedirectWriter;
use App\Verso\Importer\WordPress\TermImporter;
use App\Verso\Importer\Wxr\WxrAuthor;
use App\Verso\Importer\Wxr\WxrChannel;
use App\Verso\Importer\Wxr\WxrItem;
use Carbon\CarbonImmutable;

function partsItem(array $overrides = []): WxrItem
{
    return new WxrItem(...array_merge([
        'id' => 77, 'postType' => 'attachment', 'title' => 'Ribeira at dusk', 'slug' => 'ribeira-at-dusk', 'status' => 'inherit',
        'link' => 'https://harbourlight.example/ribeira-at-dusk/', 'content' => '', 'excerpt' => '', 'creator' => 'tomreed',
        'date' => '2024-05-09 20:15:00', 'dateGmt' => '2024-05-09 19:15:00',
        'attachmentUrl' => 'https://harbourlight.example/wp-content/uploads/2024/05/Ribeira at Dusk.JPG',
    ], $overrides));
}

function partsState(string $site = 'https://harbourlight.example'): ImportState
{
    return new ImportState(User::factory()->admin()->create(), new ImportReport, new WxrChannel(baseSiteUrl: $site));
}

describe('attachment paths', function () {
    beforeEach(function () {
        $this->attachments = new AttachmentImporter(disk: 'public', directory: 'uploads');
    });

    it('uses the attached file meta', function () {
        $item = partsItem(['meta' => ['_wp_attached_file' => '2024/05/ribeira-at-dusk.jpg']]);

        expect($this->attachments->pathFor($item))->toBe('uploads/2024/05/ribeira-at-dusk.jpg');
    });

    it('builds a clean path from the date and the URL', function () {
        expect($this->attachments->pathFor(partsItem()))->toBe('uploads/2024/05/ribeira-at-dusk.jpg');
    });

    it('never leaves the media folder', function () {
        $item = partsItem(['meta' => ['_wp_attached_file' => '../../../.env']]);

        expect($this->attachments->pathFor($item))->toBe('uploads/attachment-77.env');
    });
});

describe('authors', function () {
    it('makes an email from the login and the old site domain when it is missing', function () {
        $state = partsState();

        $user = (new AuthorImporter)->import(new WxrAuthor(5, 'Rui Almeida', '', 'Rui Almeida'), $state);

        expect($user->email)->toBe('rui.almeida@harbourlight.example')
            ->and($state->authorFor('rui almeida')->id)->toBe($user->id)
            ->and($state->userIdForWordPressId(5))->toBe($user->id);
    });

    it('uses the fallback domain when the site URL is unknown', function () {
        $user = (new AuthorImporter(fallbackDomain: 'old-blog.invalid'))->import(new WxrAuthor(6, 'joana', 'not-an-email', ''), partsState(''));

        expect($user->email)->toBe('joana@old-blog.invalid')
            ->and($user->name)->toBe('joana');
    });

    it('falls back to the person who runs the import', function () {
        $state = partsState();

        expect($state->authorFor('unknown.writer')->id)->toBe($state->fallbackAuthor->id)
            ->and($state->userIdForWordPressId(99))->toBeNull();
    });
});

describe('terms and types', function () {
    it('maps WordPress taxonomies to Verso taxonomies', function () {
        $terms = app(TermImporter::class);

        expect($terms->taxonomyFor('category'))->toBe('category')
            ->and($terms->taxonomyFor('post_tag'))->toBe('tag')
            ->and($terms->taxonomyFor('nav_menu'))->toBeNull()
            ->and($terms->taxonomyFor('post_format'))->toBeNull();
    });

    it('picks the content type and what to skip', function () {
        $entries = app(EntryImporter::class);

        expect($entries->typeFor(partsItem(['postType' => 'page'])))->toBe('page')
            ->and($entries->typeFor(partsItem(['postType' => 'product'])))->toBe('post')
            ->and($entries->shouldSkip(partsItem()))->toBeTrue()
            ->and($entries->shouldSkip(partsItem(['postType' => 'nav_menu_item', 'status' => 'publish'])))->toBeTrue()
            ->and($entries->shouldSkip(partsItem(['postType' => 'post', 'status' => 'trash'])))->toBeTrue()
            ->and($entries->shouldSkip(partsItem(['postType' => 'post', 'status' => 'publish'])))->toBeFalse();
    });
});

describe('redirect paths', function () {
    it('lists the permalink and date based paths for posts', function () {
        $imported = new ImportedEntry(1, 10, 'post', 'https://harbourlight.example/travel/a-weekend-in-lisbon/', 'a-weekend-in-lisbon', CarbonImmutable::parse('2024-03-18 09:30:00'));

        expect((new RedirectWriter)->oldPaths($imported))->toBe([
            '/travel/a-weekend-in-lisbon',
            '/2024/03/18/a-weekend-in-lisbon',
            '/2024/03/a-weekend-in-lisbon',
        ])
            ->and((new RedirectWriter(dateRedirects: false))->oldPaths($imported))->toBe(['/travel/a-weekend-in-lisbon']);
    });

    it('ignores query links and adds no date paths for pages', function () {
        $imported = new ImportedEntry(1, 20, 'page', 'https://harbourlight.example/?page_id=20', 'about', CarbonImmutable::parse('2023-06-01'));

        expect((new RedirectWriter)->oldPaths($imported))->toBe([])
            ->and((new RedirectWriter)->queryKeys($imported))->toBe(['page_id', 'p']);
    });

    it('builds legacy query paths', function () {
        expect(LegacyQueryPath::make('p', 123))->toBe('/?p=123');
    });
});
