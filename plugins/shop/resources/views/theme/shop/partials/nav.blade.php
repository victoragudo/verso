{{-- Small links between the shop pages. --}}
<nav class="shop-nav" aria-label="{{ __('shop::Shop') }}">
    <a href="{{ route('theme.shop.index') }}" @if (request()->routeIs('theme.shop.index')) aria-current="page" @endif>{{ __('shop::Shop') }}</a>
    <a href="{{ route('theme.shop.cart') }}" @if (request()->routeIs('theme.shop.cart')) aria-current="page" @endif>{{ __('shop::Cart') }}</a>
</nav>
