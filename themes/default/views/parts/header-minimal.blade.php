{{-- "Minimal" header style: only the logo and the site name, no menu. Good for landing pages. --}}
<header class="site-header is-minimal">
    <div class="wrap header-inner">
        <a class="site-title" href="{{ url('/') }}" @if (request()->is('/')) aria-current="page" @endif>
            @if ($themeLogo)
                <img class="site-logo" src="{{ $themeLogo->isResizable() ? $themeLogo->imageUrl(['h' => 96]) : $themeLogo->url() }}" alt="" height="32" @if ($themeLogo->width && $themeLogo->height) width="{{ (int) round(32 * $themeLogo->width / $themeLogo->height) }}" @endif>
            @endif
            {{ $site['name'] }}
        </a>
    </div>
</header>
