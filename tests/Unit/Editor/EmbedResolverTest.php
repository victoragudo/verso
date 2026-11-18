<?php

use App\Verso\Editor\Support\EmbedResolver;

beforeEach(function () {
    $this->resolver = new EmbedResolver('https://www.youtube-nocookie.com/embed/', 'https://player.vimeo.com/video/');
});

it('finds youtube videos in every common url shape', function (string $url) {
    $embed = $this->resolver->resolve($url);

    expect($embed?->provider)->toBe('youtube')
        ->and($embed?->id)->toBe('dQw4w9WgXcQ')
        ->and($embed?->src)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
})->with([
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'https://youtube.com/watch?v=dQw4w9WgXcQ&list=PL123',
    'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
    'http://youtu.be/dQw4w9WgXcQ',
    'https://youtu.be/dQw4w9WgXcQ/',
    'https://www.youtube.com/embed/dQw4w9WgXcQ',
    'https://www.youtube.com/shorts/dQw4w9WgXcQ',
    'https://www.youtube.com/live/dQw4w9WgXcQ',
    'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
    '  https://www.youtube.com/watch?v=dQw4w9WgXcQ  ',
]);

it('keeps the youtube start time', function (string $url, int $seconds) {
    expect($this->resolver->resolve($url)?->src)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start='.$seconds);
})->with([
    ['https://youtu.be/dQw4w9WgXcQ?t=42', 42],
    ['https://youtu.be/dQw4w9WgXcQ?t=42s', 42],
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s', 90],
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1h2m3s', 3723],
    ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=15', 15],
]);

it('ignores broken start times', function () {
    expect($this->resolver->resolve('https://youtu.be/dQw4w9WgXcQ?t=soon')?->src)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
});

it('finds vimeo videos and asks vimeo not to track', function (string $url) {
    $embed = $this->resolver->resolve($url);

    expect($embed?->provider)->toBe('vimeo')
        ->and($embed?->id)->toBe('76979871')
        ->and($embed?->src)->toBe('https://player.vimeo.com/video/76979871?dnt=1');
})->with([
    'https://vimeo.com/76979871',
    'https://www.vimeo.com/76979871/',
    'https://vimeo.com/channels/staffpicks/76979871',
    'https://vimeo.com/groups/motion/videos/76979871',
    'https://player.vimeo.com/video/76979871',
]);

it('keeps the vimeo private hash', function () {
    expect($this->resolver->resolve('https://vimeo.com/76979871/a1b2c3d4e5')?->src)->toBe('https://player.vimeo.com/video/76979871?dnt=1&h=a1b2c3d4e5')
        ->and($this->resolver->resolve('https://player.vimeo.com/video/76979871?h=a1b2c3d4e5')?->src)->toBe('https://player.vimeo.com/video/76979871?dnt=1&h=a1b2c3d4e5')
        ->and($this->resolver->resolve('https://player.vimeo.com/video/76979871?h="><script>')?->src)->toBe('https://player.vimeo.com/video/76979871?dnt=1');
});

it('returns null for other urls', function (string $url) {
    expect($this->resolver->resolve($url))->toBeNull();
})->with([
    'https://www.dailymotion.com/video/x7tgad0',
    'https://evil.example/youtube.com/watch?v=dQw4w9WgXcQ',
    'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
    'https://www.youtube.com/watch?v=short',
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ"><script>',
    'https://youtu.be/dQw4w9WgXcQ/../../evil',
    'https://www.youtube.com/channel/UC123',
    'https://vimeo.com/about',
    'javascript:alert("youtube.com/watch?v=dQw4w9WgXcQ")',
    'ftp://youtube.com/watch?v=dQw4w9WgXcQ',
    '//youtu.be/dQw4w9WgXcQ',
    'not a url',
    '',
]);

it('uses the configured player urls', function () {
    config(['verso.editor.embed.youtube_url' => 'https://www.youtube.com/embed/']);
    app()->forgetInstance(EmbedResolver::class);

    expect(app(EmbedResolver::class)->resolve('https://youtu.be/dQw4w9WgXcQ')?->src)->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
});
