{{-- One product: gallery, price, options and the "Add to cart" form, then the description. --}}
@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@section('content')
    @include('theme::shop.partials.nav')

    <article class="entry shop-product">
        <div class="shop-product__gallery">
            <x-verso-featured-image :entry="$entry" :eager="true" />
            @if ($gallery !== [])
                <ul class="shop-product__thumbs" aria-label="{{ __('shop::More photos') }}">
                    @foreach ($gallery as $image)
                        <li>
                            <img src="{{ $image['src'] }}" srcset="{{ $image['srcset'] }}" sizes="(min-width: 62rem) 9rem, 30vw" alt="{{ $image['alt'] }}" @if ($image['width']) width="{{ $image['width'] }}" @endif @if ($image['height']) height="{{ $image['height'] }}" @endif loading="lazy" decoding="async">
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="shop-product__summary">
            <header class="entry-header">
                <h1 class="page-title">{{ $entry->title }}</h1>
            </header>

            @if ($product)
                <p class="shop-price shop-price--large">{{ $storefront->priceLabel($product) }}</p>

                @if ($product->sku)
                    <p class="shop-sku">{{ __('shop::SKU :sku', ['sku' => $product->sku]) }}</p>
                @endif

                @php($stock = $product->availableStock())
                @if ($stock !== null && $stock > 0 && ! $product->hasVariants())
                    @php($stockText = \App\Verso\I18n\Translate::icu('shop::{count, plural, other {# in stock}}', ['count' => $stock]))
                    <p class="shop-stock">{{ $stockText }}</p>
                @endif

                @include('theme::shop.partials.add-to-cart', ['product' => $product, 'storefront' => $storefront])
            @else
                <p class="shop-stock shop-stock--out">{{ __('shop::This product is not for sale yet.') }}</p>
            @endif

            @if ($entry->excerpt)
                <p class="page-intro">{{ $entry->excerpt }}</p>
            @endif
        </div>

        <div class="prose shop-product__description">
            {!! $content !!}
        </div>
    </article>
@endsection
