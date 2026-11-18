{{-- Preloads the main text font. The others load only when a page uses them. --}}
@if ($newsreaderRoman && in_array('newsreader', $usedFonts, true))
    <link rel="preload" href="{{ $newsreaderRoman }}" as="font" type="font/woff2" crossorigin>
@endif
