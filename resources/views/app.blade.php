@php($i18n = $page['props']['i18n'] ?? null)
<!DOCTYPE html>
<html lang="{{ $i18n['locale'] ?? str_replace('_', '-', app()->getLocale()) }}" dir="{{ $i18n['dir'] ?? 'ltr' }}" @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="{{ match ($appearance ?? 'light') { 'dark' => 'dark', 'system' => 'light dark', default => 'light' } }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Page background before app.css loads. light-dark() follows the color-scheme meta tag above; the class rule is the fallback. --}}
        <style>
            html {
                background-color: oklch(0.985 0 0);
                background-color: light-dark(oklch(0.985 0 0), oklch(0.141 0.005 285.823));
            }

            html.dark {
                background-color: oklch(0.141 0.005 285.823);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preload" href="/fonts/figtree/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>
        @if (! empty($i18n['bundle']))
            {{-- Admin strings of the chosen language, fetched by the app before the first render. --}}
            <link rel="preload" href="{{ $i18n['bundle'] }}" as="fetch" type="application/json" crossorigin="anonymous" data-verso-i18n>
        @endif

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $page['props']['siteName'] ?? config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
