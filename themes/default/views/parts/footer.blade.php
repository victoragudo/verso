{{-- Site footer with the tagline, the footer menu and search. Child themes can replace this part. --}}
<footer class="site-footer">
    <div class="wrap footer-inner">
        <p>
            <strong>{{ $site['name'] }}</strong>
            @if ($site['tagline'] !== '')
                · {{ $site['tagline'] }}
            @endif
            · <a href="{{ url('/feed') }}">{{ __('default::RSS') }}</a>
        </p>
        @if (($themeSettings['footer_text'] ?? '') !== '')
            <div class="footer-text">@shortcodes($themeSettings['footer_text'], 'footer')</div>
        @endif
        <x-verso-menu location="footer" :label="__('default::Footer')" class="footer-nav" />
        @include('theme::partials.search-form', ['search' => '', 'inputId' => 'footer-search'])
    </div>
</footer>
