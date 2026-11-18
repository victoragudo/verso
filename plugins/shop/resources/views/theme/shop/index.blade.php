{{-- The shop page: every product for sale. --}}
@extends('theme::layouts.app')

@section('title', __('shop::Shop'))

@section('content')
    <header class="page-header shop-header">
        <h1 class="page-title">{{ __('shop::Shop') }}</h1>
        @include('theme::shop.partials.nav')
    </header>

    @if ($categories->isNotEmpty())
        <nav class="shop-categories" aria-label="{{ __('shop::Product categories') }}">
            @foreach ($categories as $category)
                <a href="{{ $category->path() }}">{{ $category->name }}</a>
            @endforeach
        </nav>
    @endif

    @if ($products->isEmpty())
        <p class="empty">{{ __('shop::There are no products for sale yet.') }}</p>
    @else
        <form class="shop-sort" method="get" action="{{ route('theme.shop.index') }}">
            <label for="shop-sort">{{ __('shop::Sort by') }}</label>
            <select id="shop-sort" name="sort">
                @foreach ($sorts as $value => $label)
                    <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit">{{ __('shop::Sort') }}</button>
        </form>

        <div class="shop-grid shop-grid--3">
            @foreach ($products as $product)
                @include('theme::shop.partials.product-card', ['product' => $product, 'storefront' => $storefront])
            @endforeach
        </div>

        @if ($products->hasPages())
            <nav class="pagination" aria-label="{{ __('shop::Pages') }}">
                @if ($products->previousPageUrl())
                    <a href="{{ $products->previousPageUrl() }}" rel="prev">{{ __('shop::Newer products') }}</a>
                @else
                    <span></span>
                @endif
                <span>{{ __('shop::Page :page of :pages', ['page' => $products->currentPage(), 'pages' => $products->lastPage()]) }}</span>
                @if ($products->nextPageUrl())
                    <a href="{{ $products->nextPageUrl() }}" rel="next">{{ __('shop::More products') }}</a>
                @endif
            </nav>
        @endif
    @endif
@endsection
