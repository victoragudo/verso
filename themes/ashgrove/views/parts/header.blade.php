{{-- Site header: logo or site name, the main menu and a search link. The menu is one list: inline when it fits, a popover when it does not. --}}
@inject('menuLinks', 'App\Verso\Site\Menus\MenuRenderer')
@php
    $links = $hasPrimaryMenu ? $menuLinks->links(App\Verso\Theme\Support\ThemeComposer::PRIMARY_MENU, request()->url()) : [];
    $blogUrl = $hasPrimaryMenu ? null : app(App\Verso\Site\Permalinks\Permalinks::class)->archiveUrl('post');
@endphp
<header @class(['site-header', 'is-centered' => ($headerStyle ?? null) === 'centered']) data-site-header>
    <div class="header-inner">
        <a class="brand" href="{{ url('/') }}" @if (request()->is('/')) aria-current="page" @endif>
            @if ($themeLogo)
                <img class="brand-logo" src="{{ $themeLogo->isResizable() ? $themeLogo->imageUrl(['h' => 88]) : $themeLogo->url() }}" alt="{{ $site['name'] }}" height="44" @if ($themeLogo->width && $themeLogo->height) width="{{ (int) round(44 * $themeLogo->width / $themeLogo->height) }}" @endif>
            @else
                {{ $site['name'] }}
            @endif
        </a>

        <nav class="site-nav" aria-label="{{ __('ashgrove::Main') }}">
            <button class="menu-button" type="button" popovertarget="site-menu" aria-controls="site-menu">
                <svg aria-hidden="true" viewBox="0 0 20 20" width="20" height="20"><path d="M3 6h14M3 10h14M3 14h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                <span>{{ __('ashgrove::Menu') }}</span>
            </button>
            <ul id="site-menu" class="site-menu" popover="auto">
                @if ($hasPrimaryMenu)
                    @foreach ($links as $link)
                        <li @class(['has-children' => $link['children'] !== [], 'is-mega' => $link['mega'] ?? false])>
                            <a href="{{ $link['url'] }}" @if ($link['active']) aria-current="page" @endif @if ($link['new_tab']) target="_blank" @endif @if (($rel = App\Verso\Site\Menus\MenuRenderer::rel($link)) !== null) rel="{{ $rel }}" @endif>{{ $link['label'] }}</a>
                            @if ($link['children'] !== [])
                                <button class="submenu-button" type="button" aria-expanded="false" aria-controls="submenu-{{ $loop->index }}" data-submenu>
                                    <span class="visually-hidden">{{ __('ashgrove::More in :name', ['name' => $link['label']]) }}</span>
                                    <svg aria-hidden="true" viewBox="0 0 12 12" width="12" height="12"><path d="M3 4.5 6 7.5l3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <ul id="submenu-{{ $loop->index }}" class="submenu">
                                    @foreach ($link['children'] as $child)
                                        <li><a href="{{ $child['url'] }}" @if ($child['active']) aria-current="page" @endif @if ($child['new_tab']) target="_blank" @endif @if (($childRel = App\Verso\Site\Menus\MenuRenderer::rel($child)) !== null) rel="{{ $childRel }}" @endif>{{ $child['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                @else
                    @if ($blogUrl !== null)
                        <li><a href="{{ $blogUrl }}" @if (rtrim(url()->current(), '/') === rtrim($blogUrl, '/')) aria-current="page" @endif>{{ __('ashgrove::Blog') }}</a></li>
                    @endif
                    @foreach ($navigation as $page)
                        <li><a href="{{ $page->url() }}" @if (url()->current() === $page->url()) aria-current="page" @endif>{{ $page->title }}</a></li>
                    @endforeach
                @endif
            </ul>
        </nav>

        <a class="search-link" href="{{ route('theme.search') }}" @if (request()->routeIs('theme.search')) aria-current="page" @endif>
            <svg aria-hidden="true" viewBox="0 0 20 20" width="20" height="20"><circle cx="8.5" cy="8.5" r="5.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m13 13 4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <span class="visually-hidden">{{ __('ashgrove::Search') }}</span>
        </a>
    </div>
</header>
