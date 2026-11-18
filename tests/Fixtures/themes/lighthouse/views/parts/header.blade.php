<header class="site-header">
    <div class="wrap header-inner">
        <a class="site-title" href="{{ url('/') }}">
            @if ($themeLogo)
                <img src="{{ $themeLogo->url() }}" alt="" width="32" height="32">
            @endif
            {{ $site['name'] }}
        </a>

        @if ($hasPrimaryMenu)
            <x-verso-menu location="primary" :label="__('lighthouse::Main')" class="site-nav" />
        @else
            <nav class="site-nav" aria-label="{{ __('lighthouse::Main') }}">
                <ul>
                    @foreach ($navigation as $page)
                        <li><a href="{{ $page->url() }}">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
</header>
