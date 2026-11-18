{{-- Subtotal, shipping, tax and total of a cart or an order. --}}
@php($currency ??= null)
<dl class="shop-totals">
    <div><dt>{{ __('shop::Subtotal') }}</dt><dd>{{ $storefront->format($totals->subtotal, $currency) }}</dd></div>
    <div><dt>{{ __('shop::Shipping') }}</dt><dd>{{ $totals->shipping === 0 ? __('shop::Free') : $storefront->format($totals->shipping, $currency) }}</dd></div>
    @if ($totals->taxRate > 0)
        <div><dt>{{ __('shop::Tax (:rate%)', ['rate' => rtrim(rtrim(number_format($totals->taxPercent(), 2), '0'), '.')]) }}</dt><dd>{{ $storefront->format($totals->tax, $currency) }}</dd></div>
    @endif
    <div class="shop-totals__total"><dt>{{ __('shop::Total') }}</dt><dd>{{ $storefront->format($totals->total(), $currency) }}</dd></div>
</dl>
