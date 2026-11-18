{{-- Site header with the logo, the site name and the main menu. Child themes can replace this part. --}}
<header class="site-header">
    <div class="wrap header-inner">
        <a class="site-title" href="{{ url('/') }}" @if (request()->is('/')) aria-current="page" @endif>
            @if ($themeLogo)
                <img class="site-logo" src="{{ $themeLogo->isResizable() ? $themeLogo->imageUrl(['h' => 96]) : $themeLogo->url() }}" alt="" height="32" @if ($themeLogo->width && $themeLogo->height) width="{{ (int) round(32 * $themeLogo->width / $themeLogo->height) }}" @endif>
            @endif
            {{ $site['name'] }}
        </a>

        @if ($hasPrimaryMenu)
            <x-verso-menu :location="App\Verso\Theme\Support\ThemeComposer::PRIMARY_MENU" label="Main" class="site-nav" />
        @else
            <nav class="site-nav" aria-label="Main">
                <ul>
                    @php($blogUrl = app(App\Verso\Site\Permalinks\Permalinks::class)->archiveUrl('post'))
                    @if ($blogUrl !== null)
                        <li><a href="{{ $blogUrl }}" @if (rtrim(url()->current(), '/') === rtrim($blogUrl, '/')) aria-current="page" @endif>Blog</a></li>
                    @endif
                    @foreach ($navigation as $page)
                        <li><a href="{{ $page->url() }}" @if (url()->current() === $page->url()) aria-current="page" @endif>{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
</header>
