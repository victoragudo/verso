<x-mail::message>
# {{ __('shop::Order :number: :status', ['number' => $order->number(), 'status' => $order->status->label()]) }}

{{ __('shop::Hi :name,', ['name' => $order->customer_name]) }}

{{ $message }}

@include('shop::mail.order-lines')

<x-mail::button :url="$order->url()">
{{ __('shop::See your order') }}
</x-mail::button>

{{ $siteName }}
</x-mail::message>
