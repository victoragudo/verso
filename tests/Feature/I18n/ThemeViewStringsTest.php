<?php

use Symfony\Component\Finder\Finder;

// Blade views that visitors see. Their text must go through __() so it follows the site language.
function visitorViews(): array
{
    $folders = array_filter([
        ...glob(base_path('themes/*/views')) ?: [],
        ...glob(base_path('plugins/*/resources/views/theme')) ?: [],
        base_path('resources/views/verso/comments'),
        base_path('resources/views/verso/site'),
        base_path('resources/views/verso/media'),
        base_path('resources/views/verso/layouts'),
    ], is_dir(...));

    $files = [];

    foreach (Finder::create()->files()->in($folders)->name('*.blade.php') as $file) {
        $files[] = $file->getPathname();
    }

    sort($files);

    return $files;
}

// The view without comments, scripts, styles and PHP, so only HTML and its text are left.
function visitorMarkup(string $view): string
{
    $patterns = [
        '/\{\{--.*?--\}\}/s',
        '#<(script|style)\b.*?</\1>#si',
        '/@php\b(?!\s*\().*?@endphp/s',
        '/\{!!.*?!!\}/s',
        '/\{\{.*?\}\}/s',
        '/@[a-zA-Z]+\s*(\((?:[^()\'"]|\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|(?1))*\))?/',
    ];

    return (string) preg_replace($patterns, ' ', $view);
}

// English text between tags and in attributes people read, like aria-label and placeholder.
function englishInMarkup(string $markup): array
{
    $found = [];

    preg_match_all('/(?<![:\w-])(aria-label|placeholder|title|alt|label)="([^"]*\p{L}\p{Ll}[^"]*)"/u', $markup, $attributes, PREG_SET_ORDER);

    foreach ($attributes as $match) {
        $found[] = $match[1].'="'.$match[2].'"';
    }

    // Only text between two tags: CSS or script partials without tags are not text.
    $pieces = preg_split('/<[^>]*>/', $markup) ?: [];

    foreach (array_slice($pieces, 1, -1) as $piece) {
        if (preg_match('/\p{L}{2}/u', $piece)) {
            $found[] = trim((string) preg_replace('/\s+/', ' ', $piece));
        }
    }

    return $found;
}

// Sentences written as PHP strings in echoes and directives, like {{ $empty ?? 'Nothing here.' }}, outside __().
function englishInPhp(string $view): array
{
    $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $view);
    preg_match_all('/(?<![\w:>$])(__|trans_choice|@lang|Translate::text|Translate::icu)?\(?\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $view, $strings, PREG_SET_ORDER);
    $found = [];

    foreach ($strings as $match) {
        $text = $match[2];

        if (($match[1] ?? '') === '' && preg_match('/^\p{Lu}\p{Ll}+(\s|[.,!?]|$)/u', $text) && ! str_contains($text, '\\')) {
            $found[] = $text;
        }
    }

    return $found;
}

it('finds English text in a view', function () {
    $view = <<<'BLADE'
        @section('title', 'Page not found')
        <a href="#content" class="skip-link">Skip to content</a>
        <nav aria-label="Main" :label="$label">{{ __('lighthouse::Older posts') }}</nav>
        <p>{{ $empty ?? 'Nothing has been published here yet.' }}</p>
        <p>{{ __('lighthouse::Page :current of :last', ['current' => 1, 'last' => 2]) }}</p>
        BLADE;

    expect(englishInMarkup(visitorMarkup($view)))->toBe(['aria-label="Main"', 'Skip to content'])
        ->and(englishInPhp($view))->toBe(['Page not found', 'Nothing has been published here yet.']);
});

it('keeps English out of the views visitors see', function () {
    $problems = [];

    foreach (visitorViews() as $path) {
        $view = (string) file_get_contents($path);
        $relative = str_replace(base_path().'/', '', $path);

        foreach ([...englishInMarkup(visitorMarkup($view)), ...englishInPhp($view)] as $text) {
            $problems[] = "{$relative}: {$text}";
        }
    }

    expect($problems)->toBe([], 'Wrap these texts in __() with the theme or plugin prefix, like __(\'ashgrove::Search\').');
});
