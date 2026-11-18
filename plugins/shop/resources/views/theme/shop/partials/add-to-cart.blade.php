{{-- The "Add to cart" form, with an option picker for products with variants. --}}
@php($formId = 'shop-add-'.$product->id)
@if ($product->inStock())
    <form class="shop-buy" method="post" action="{{ route('theme.shop.cart.add') }}">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">

        @if ($product->hasVariants())
            <div class="shop-field">
                <label for="{{ $formId }}-variant">{{ $product->variants->first()->option_name }}</label>
                <select id="{{ $formId }}-variant" name="variant_id" required>
                    <option value="">{{ __('shop::Choose an option') }}</option>
                    @foreach ($product->variants as $variant)
                        <option value="{{ $variant->id }}" @disabled(! $product->inStock($variant)) @selected((string) old('variant_id') === (string) $variant->id)>
                            {{ $variant->option_value }} · {{ $storefront->format($product->priceFor($variant)) }}@unless ($product->inStock($variant)) · {{ __('shop::Out of stock') }} @endunless
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="shop-buy__row">
            <div class="shop-field shop-field--quantity">
                <label for="{{ $formId }}-quantity">{{ __('shop::Quantity') }}</label>
                <input id="{{ $formId }}-quantity" type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" max="{{ $product->availableStock() ?? config('shop.max_quantity') }}" inputmode="numeric">
            </div>
            <button class="shop-button" type="submit">{{ __('shop::Add to cart') }}</button>
        </div>
    </form>
@else
    <p class="shop-stock shop-stock--out">{{ __('shop::Out of stock') }}</p>
@endif
