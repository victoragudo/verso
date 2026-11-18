{{-- Products of one product category, like "/product-category/mugs". --}}
@extends('theme::layouts.app')

@section('title', $term->name)
@section('description', $term->description ?: __('shop:::taxonomy: :term', ['taxonomy' => \App\Verso\I18n\Translate::text($taxonomy->label, plugin: 'shop'), 'term' => $term->name]))

@section('content')
    <header class="page-header shop-header">
        <p class="eyebrow">{{ \App\Verso\I18n\Translate::text($taxonomy->label, plugin: 'shop') }}</p>
        <h1 class="page-title">{{ $term->name }}</h1>
        @if ($term->description)
            <p class="page-intro">{{ $term->description }}</p>
        @endif
        @include('theme::shop.partials.nav')
    </header>

    @if ($subcategories->isNotEmpty())
        <nav class="shop-categories" aria-label="{{ \App\Verso\I18n\Translate::text($taxonomy->pluralLabel, plugin: 'shop') }}">
            @foreach ($subcategories as $child)
                <a href="{{ $child->path() }}">{{ $child->name }}</a>
            @endforeach
        </nav>
    @endif

    @if ($products->isEmpty())
        <p class="empty">{{ __('shop::There are no products in :category yet.', ['category' => $term->name]) }}</p>
    @else
        <form class="shop-sort" method="get" action="{{ $term->path() }}">
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
