{{-- Order lines and totals, shared by the shop emails. --}}
<x-mail::table>
| {{ __('shop::Product') }} | {{ __('shop::Qty') }} | {{ __('shop::Total') }} |
|:--------|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->name }}@if ($item->variant_label) ({{ $item->variant_label }})@endif | {{ $item->quantity }} | {{ $money->format($item->line_total, $order->currency) }} |
@endforeach
| {{ __('shop::Subtotal') }} | | {{ $money->format($order->subtotal, $order->currency) }} |
| {{ __('shop::Shipping') }} | | {{ $money->format($order->shipping_total, $order->currency) }} |
| {{ __('shop::Tax') }} | | {{ $money->format($order->tax_total, $order->currency) }} |
| **{{ __('shop::Total') }}** | | **{{ $money->format($order->total, $order->currency) }}** |
</x-mail::table>
