<?php

use App\Verso\Updates\Enums\UpdateChannel;
use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Feed\Release;
use App\Verso\Updates\Feed\ReleaseFeed;
use Illuminate\Support\Facades\Http;
use Tests\Support\UpdateSandbox;

beforeEach(function () {
    Http::preventStrayRequests();
});

function releaseFeed(string $url = 'https://api.github.com/repos/victoragudo/verso/releases'): ReleaseFeed
{
    return new ReleaseFeed(
        url: $url,
        timeout: 5,
        assetName: 'verso-{version}.zip',
        checksumSuffix: '.sha256',
        signatureSuffix: '.sig',
        securityMarker: '[security]',
        downloadSchemes: ['https'],
    );
}

it('parses GitHub releases into releases with package urls', function () {
    $item = UpdateSandbox::githubRelease('1.2.0');
    $item['assets'][] = ['name' => 'verso-1.2.0.zip.sig', 'browser_download_url' => UpdateSandbox::packageUrl('1.2.0').'.sig', 'size' => 88];

    $releases = releaseFeed()->parse([$item]);

    expect($releases)->toHaveCount(1)
        ->and($releases[0]->version)->toBe('1.2.0')
        ->and($releases[0]->name)->toBe('Verso 1.2.0')
        ->and($releases[0]->notes)->toContain('Better editor shortcuts')
        ->and($releases[0]->packageUrl)->toBe(UpdateSandbox::packageUrl('1.2.0'))
        ->and($releases[0]->checksumUrl)->toBe(UpdateSandbox::packageUrl('1.2.0').'.sha256')
        ->and($releases[0]->signatureUrl)->toBe(UpdateSandbox::packageUrl('1.2.0').'.sig')
        ->and($releases[0]->packageSize)->toBe(18_350_112)
        ->and($releases[0]->pageUrl)->toBe('https://github.com/victoragudo/verso/releases/tag/v1.2.0')
        ->and($releases[0]->preRelease)->toBeFalse()
        ->and($releases[0]->security)->toBeFalse()
        ->and($releases[0]->packageName())->toBe('verso-1.2.0.zip');
});

it('sorts releases newest first and skips drafts, bad tags and releases without a package', function () {
    $releases = releaseFeed()->parse([
        UpdateSandbox::githubRelease('1.1.0'),
        UpdateSandbox::githubRelease('1.3.0', ['draft' => true]),
        UpdateSandbox::githubRelease('1.2.0'),
        UpdateSandbox::githubRelease('1.0.4', ['tag_name' => 'nightly-build']),
        UpdateSandbox::githubRelease('1.2.5', ['assets' => []]),
        UpdateSandbox::githubRelease('1.1.9', ['assets' => [['name' => 'verso-1.1.9.zip', 'browser_download_url' => 'http://insecure.example/verso-1.1.9.zip']]]),
        'not a release',
    ]);

    expect(array_map(fn (Release $release) => $release->version, $releases))->toBe(['1.2.0', '1.1.0']);
});

it('marks pre-releases from the GitHub flag or the version', function () {
    $releases = releaseFeed()->parse([
        UpdateSandbox::githubRelease('2.0.0-beta.1'),
        UpdateSandbox::githubRelease('1.9.0', ['prerelease' => true]),
    ]);

    expect($releases[0]->preRelease)->toBeTrue()
        ->and($releases[1]->preRelease)->toBeTrue();
});

it('marks security releases from the marker in the title or notes', function () {
    $releases = releaseFeed()->parse([
        UpdateSandbox::githubRelease('1.2.1', ['name' => 'Verso 1.2.1 [Security]']),
        UpdateSandbox::githubRelease('1.2.2', ['body' => 'Fixes a stored XSS in comments. [security]']),
        UpdateSandbox::githubRelease('1.2.3'),
    ]);

    expect(array_map(fn (Release $release) => $release->security, $releases))->toBe([false, true, true]);
});

it('picks the newest release for each channel', function () {
    $feed = releaseFeed();
    $releases = $feed->parse([
        UpdateSandbox::githubRelease('1.3.0-rc.1'),
        UpdateSandbox::githubRelease('1.2.0'),
    ]);

    expect($feed->latestFor($releases, UpdateChannel::Stable)?->version)->toBe('1.2.0')
        ->and($feed->latestFor($releases, UpdateChannel::Beta)?->version)->toBe('1.3.0-rc.1')
        ->and($feed->latestFor([], UpdateChannel::Stable))->toBeNull();
});

it('accepts an object with a releases list', function () {
    expect(releaseFeed()->parse(['releases' => [UpdateSandbox::githubRelease('1.4.0')]]))->toHaveCount(1);
});

it('reads the feed over HTTP with the GitHub accept header', function () {
    Http::fake(['api.github.com/*' => Http::response([UpdateSandbox::githubRelease('1.2.0')])]);

    expect(releaseFeed()->releases())->toHaveCount(1);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/victoragudo/verso/releases'
        && $request->hasHeader('Accept', 'application/vnd.github+json'));
});

it('turns feed errors into a readable failure', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'API rate limit exceeded'], 403)]);

    releaseFeed()->releases();
})->throws(UpdateFailed::class, 'The release feed could not be reached');

it('does nothing without a feed url', function () {
    $feed = releaseFeed('');

    expect($feed->isConfigured())->toBeFalse()
        ->and($feed->releases())->toBe([]);

    Http::assertNothingSent();
});

it('keeps release data through toArray and fromArray', function () {
    $release = UpdateSandbox::release('1.2.0', signed: true, size: 2048);

    expect(Release::fromArray($release->toArray()))->toEqual($release)
        ->and(Release::fromArray(['version' => 'soon', 'package_url' => 'https://example.com/a.zip']))->toBeNull()
        ->and(Release::fromArray(['version' => '1.0.0']))->toBeNull();
});
