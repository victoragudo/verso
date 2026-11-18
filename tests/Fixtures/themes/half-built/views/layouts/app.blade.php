{{-- Main layout of Half Built. --}}
<!DOCTYPE html>
<html lang="{{ $site['locale'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @hasSection('seo')
        @yield('seo')
    @else
        <x-verso-seo :title="View::hasSection('title') ? trim(View::yieldContent('title')) : null" />
    @endif
    @stack('head')
    <x-verso-head />
    <style>{!! $themeStyles !!}</style>
</head>
<body class="{{ $bodyClass ?? '' }}">
    <a class="skip-link" href="#content">Skip to content</a>
    <header class="site-header">
        <a href="{{ url('/') }}" class="site-name">{{ $site['name'] }}</a>
    </header>
    <main id="content" tabindex="-1">
        @yield('content')
    </main>
    <footer class="site-footer">
        <img src="/favicon.ico" alt="" width="24" height="24">
        <p>{{ $site['name'] }}</p>
    </footer>
    <x-verso-footer />
</body>
</html>
