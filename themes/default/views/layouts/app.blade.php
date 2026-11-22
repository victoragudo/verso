{{-- Main layout of the default theme. Every page extends it. --}}
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
    @stack('head')
    <x-verso-head />
    <style>{!! $themeStyles !!}</style>
</head>
<body class="{{ $bodyClass ?? '' }}">
    <a href="#content" class="skip-link">{{ __('default::Skip to content') }}</a>

    @templatePart('header')

    <main id="content" class="wrap" tabindex="-1">
        @yield('content')
    </main>

    @templatePart('footer')
    <x-verso-footer />
</body>
</html>
