<x-mail::message>
@if ($forShop)
# {{ __('shop::New order :number', ['number' => $order->number()]) }}

{{ __('shop:::name (:email) placed an order paid with :payment.', ['name' => $order->customer_name, 'email' => $order->customer_email, 'payment' => $paymentLabel]) }}
@else
# {{ __('shop::Thank you for your order') }}

{!! __('shop::Hi :name, we received your order **:number**.', ['name' => e($order->customer_name), 'number' => e($order->number())]) !!}
@endif

@include('shop::mail.order-lines')

@if (! $forShop && $instructions !== '')
## {{ __('shop::How to pay') }}

{{ $instructions }}

{!! __('shop::Use **:number** as the payment reference.', ['number' => e($order->number())]) !!}
@endif

**{{ __('shop::Ship to') }}**<br>
@foreach ($order->addressLines() as $line)
{{ $line }}<br>
@endforeach

@unless ($forShop)
<x-mail::button :url="$order->url()">
{{ __('shop::See your order') }}
</x-mail::button>
@endunless

{{ $siteName }}
</x-mail::message>
