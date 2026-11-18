{{-- "Minimal" header style: only the logo or the site name, no menu. Good for landing pages. --}}
<header class="site-header is-minimal">
    <div class="header-inner">
        <a class="brand" href="{{ url('/') }}" @if (request()->is('/')) aria-current="page" @endif>
            @if ($themeLogo)
                <img class="brand-logo" src="{{ $themeLogo->isResizable() ? $themeLogo->imageUrl(['h' => 88]) : $themeLogo->url() }}" alt="{{ $site['name'] }}" height="44" @if ($themeLogo->width && $themeLogo->height) width="{{ (int) round(44 * $themeLogo->width / $themeLogo->height) }}" @endif>
            @else
                {{ $site['name'] }}
            @endif
        </a>
    </div>
</header>
