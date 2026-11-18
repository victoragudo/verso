{{-- "Four columns" footer style: about the site, the footer menu, reading links and the copyright. --}}
<footer class="site-footer is-columns">
    <div class="footer-inner footer-columns">
        <div class="footer-about">
            <p class="footer-name">{{ $site['name'] }}</p>
            @if (($themeSettings['footer_text'] ?? '') !== '')
                <p>{{ $themeSettings['footer_text'] }}</p>
            @elseif ($site['tagline'] !== '')
                <p>{{ $site['tagline'] }}</p>
            @endif
        </div>
        <div class="footer-column">
            <h2 class="footer-heading">Pages</h2>
            <x-verso-menu location="footer" label="Footer" class="footer-nav" />
        </div>
        <div class="footer-column">
            <h2 class="footer-heading">Read</h2>
            <ul class="footer-links">
                <li><a href="{{ app(App\Verso\Site\Permalinks\Permalinks::class)->archiveUrl('post') }}">All posts</a></li>
                <li><a href="{{ route('theme.search') }}">Search</a></li>
                <li><a href="{{ url('/feed') }}">RSS feed</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h2 class="footer-heading">About</h2>
            <p class="footer-legal"><small>© {{ now()->year }} {{ $site['name'] }}</small></p>
        </div>
    </div>
</footer>
