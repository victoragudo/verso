{{-- One level of a menu. Calls itself for sub items. --}}
<ul class="verso-menu__list verso-menu__list--depth-{{ $depth }}">
    @foreach ($links as $link)
        @php($rel = App\Verso\Site\Menus\MenuRenderer::rel($link))
        <li @class(['verso-menu__item', 'verso-menu__item--active' => $link['active'], 'verso-menu__item--has-children' => $link['children'] !== [], 'verso-menu__item--mega' => $link['mega'] ?? false])>
            <a href="{{ $link['url'] }}"{!! $link['active'] ? ' aria-current="page"' : '' !!}{!! $link['new_tab'] ? ' target="_blank"' : '' !!}@if ($rel !== null) rel="{{ $rel }}"@endif>{{ $link['label'] }}</a>
            @if ($link['children'] !== [])
                @include('verso-site::menu-items', ['links' => $link['children'], 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ul>
