{{-- Site footer: a short line about the site, the footer menu and the copyright. --}}
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-about">
            <p class="footer-name">{{ $site['name'] }}</p>
            @if (($themeSettings['footer_text'] ?? '') !== '')
                <p>{{ $themeSettings['footer_text'] }}</p>
            @elseif ($site['tagline'] !== '')
                <p>{{ $site['tagline'] }}</p>
            @endif
        </div>
        <x-verso-menu location="footer" :label="__('ashgrove::Footer')" class="footer-nav" />
        <p class="footer-legal">
            <small>© {{ now()->year }} {{ $site['name'] }}</small>
            <a href="{{ url('/feed') }}">{{ __('ashgrove::RSS feed') }}</a>
        </p>
    </div>
</footer>
