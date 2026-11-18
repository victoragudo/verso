{{-- Main layout of the Ashgrove theme. Every page extends it. --}}
@inject('themeFiles', 'App\Verso\Theme\ThemeManager')
@php
    // Font files from the theme folder, with a version in the URL.
    $usedFonts = [$themeSettings['heading_font'] ?? 'newsreader', $themeSettings['body_font'] ?? 'newsreader'];
    $newsreaderRoman = $themeFiles->assetUrl('fonts/newsreader-roman.woff2');
    $newsreaderItalic = $themeFiles->assetUrl('fonts/newsreader-italic.woff2');
    $instrumentSans = $themeFiles->assetUrl('fonts/instrument-sans.woff2');
@endphp
<!DOCTYPE html>
<html lang="{{ $site['locale'] }}" dir="{{ $site['dir'] ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @hasSection('seo')
        @yield('seo')
    @else
        <x-verso-seo :title="View::hasSection('title') ? trim(View::yieldContent('title')) : null" :description="View::hasSection('description') ? trim(View::yieldContent('description')) : null" />
    @endif
    <meta name="color-scheme" content="light dark">
    <link rel="alternate" type="application/rss+xml" title="{{ $site['name'] }}" href="{{ url('/feed') }}">
    @include('theme::partials.fonts')
    @stack('head')
    <x-verso-head />
    <style>@include('theme::partials.font-faces'){!! $themeStyles !!}</style>
</head>
<body class="{{ $bodyClass ?? '' }}">
    <a class="skip-link" href="#content">Skip to content</a>

    @templatePart('header')

    <main id="content" tabindex="-1">
        @yield('content')
    </main>

    @templatePart('footer')
    @include('theme::partials.nav-script')
    <x-verso-footer />
</body>
</html>
