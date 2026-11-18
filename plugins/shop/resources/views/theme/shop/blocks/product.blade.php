{{-- Product block: one product inside an entry. --}}
@php($image = $storefront->image($product, '(min-width: 40rem) 16rem, 100vw'))
<div class="shop-block-product">
    @if ($image)
        <a class="shop-block-product__media" href="{{ $product->entry->url() }}">
            <img src="{{ $image['src'] }}" srcset="{{ $image['srcset'] }}" sizes="{{ $image['sizes'] }}" alt="{{ $image['alt'] }}" @if ($image['width']) width="{{ $image['width'] }}" @endif @if ($image['height']) height="{{ $image['height'] }}" @endif loading="lazy" decoding="async">
        </a>
    @endif
    <div class="shop-block-product__body">
        <h3 class="shop-block-product__title"><a href="{{ $product->entry->url() }}">{{ $product->entry->title }}</a></h3>
        @if ($product->entry->excerpt)
            <p>{{ $product->entry->excerpt }}</p>
        @endif
        <p class="shop-price">{{ $storefront->priceLabel($product) }}</p>
        @if ($showButton)
            @include('theme::shop.partials.add-to-cart', ['product' => $product, 'storefront' => $storefront])
        @endif
    </div>
</div>
