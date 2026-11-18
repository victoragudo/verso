{{-- One product in a grid: image, name, price and stock. --}}
@php($image = $storefront->image($product, '(min-width: 62rem) 18rem, (min-width: 40rem) 45vw, 100vw'))
<article class="shop-card">
    <a class="shop-card__link" href="{{ $product->entry->url() }}">
        <div class="shop-card__media">
            @if ($image)
                <img src="{{ $image['src'] }}" srcset="{{ $image['srcset'] }}" sizes="{{ $image['sizes'] }}" alt="{{ $image['alt'] }}" @if ($image['width']) width="{{ $image['width'] }}" @endif @if ($image['height']) height="{{ $image['height'] }}" @endif loading="lazy" decoding="async">
            @endif
        </div>
        <h2 class="shop-card__title">{{ $product->entry->title }}</h2>
    </a>
    <p class="shop-card__meta">
        <span class="shop-price">{{ $storefront->priceLabel($product) }}</span>
        @unless ($product->inStock())
            <span class="shop-stock shop-stock--out">{{ __('shop::Out of stock') }}</span>
        @endunless
    </p>
</article>
