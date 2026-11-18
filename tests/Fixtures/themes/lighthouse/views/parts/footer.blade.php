<footer class="site-footer">
    <div class="wrap">
        <p>{{ $site['name'] }}</p>
        @if (($themeSettings['footer_text'] ?? '') !== '')
            <p>{{ $themeSettings['footer_text'] }}</p>
        @endif
        <x-verso-menu location="footer" :label="__('lighthouse::Footer')" />
        <form role="search" method="get" action="{{ route('theme.search') }}">
            <label for="footer-search">{{ __('lighthouse::Search the site') }}</label>
            <input id="footer-search" type="search" name="q" required>
            <button type="submit">{{ __('lighthouse::Search') }}</button>
        </form>
    </div>
</footer>
