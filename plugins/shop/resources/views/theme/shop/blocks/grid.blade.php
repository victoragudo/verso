{{-- Product grid block. --}}
<div class="shop-grid shop-grid--{{ $columns }}">
    @foreach ($products as $product)
        @include('theme::shop.partials.product-card', ['product' => $product, 'storefront' => $storefront])
    @endforeach
</div>
