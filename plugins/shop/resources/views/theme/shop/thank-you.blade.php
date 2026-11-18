{{-- The order page after the checkout: thank you, status, how to pay and the order lines. --}}
@extends('theme::layouts.app')

@section('title', __('shop::Order :number', ['number' => $order->number()]))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <header class="page-header">
        <p class="eyebrow">{{ __('shop::Order :number', ['number' => $order->number()]) }}</p>
        <h1 class="page-title">
            @if ($order->status->isPaid())
                {{ __('shop::Thank you, your order is confirmed') }}
            @elseif ($order->status === Verso\Plugins\Shop\Enums\OrderStatus::Pending)
                {{ __('shop::Thank you for your order') }}
            @else
                @php($statusTitle = \App\Verso\I18n\Translate::icu('shop::{status, select, cancelled {Your order is cancelled} refunded {Your order is refunded} other {Your order is {label}}}', ['status' => $order->status->value, 'label' => $order->status->label()]))
                {{ $statusTitle }}
            @endif
        </h1>
        <p class="page-intro">{{ __('shop::We sent the details to :email.', ['email' => $order->customer_email]) }}</p>
    </header>

    @include('theme::shop.partials.notices')

    <div class="shop-layout">
        <div>
            <p class="shop-status">{!! __('shop::Status: <strong>:status</strong> · Payment: :payment', ['status' => e($order->status->label()), 'payment' => e($paymentLabel)]) !!}</p>

            @if ($instructions !== '')
                <section class="shop-instructions">
                    <h2>{{ __('shop::How to pay') }}</h2>
                    <p>{!! nl2br(e($instructions)) !!}</p>
                    <p>{!! __('shop::Please use <strong>:number</strong> as the payment reference.', ['number' => e($order->number())]) !!}</p>
                </section>
            @endif

            @if ($canPay)
                <form method="post" action="{{ route('theme.shop.order.pay', ['token' => $order->token]) }}">
                    @csrf
                    <p>{{ __('shop::Your payment is not finished yet.') }}</p>
                    <button class="shop-button" type="submit">{{ __('shop::Pay now') }}</button>
                </form>
            @endif

            <h2>{{ __('shop::Shipping to') }}</h2>
            <address class="shop-address">
                @foreach ($order->addressLines() as $line)
                    {{ $line }}<br>
                @endforeach
            </address>
        </div>

        <aside class="shop-summary" aria-label="{{ __('shop::Order summary') }}">
            <h2>{{ __('shop::Your order') }}</h2>
            <ul class="shop-summary__lines">
                @foreach ($order->items as $item)
                    <li>
                        <span>{{ $item->quantity }} × {{ $item->name }}@if ($item->variant_label) <span class="shop-muted">({{ $item->variant_label }})</span>@endif</span>
                        <span>{{ $storefront->format($item->line_total, $order->currency) }}</span>
                    </li>
                @endforeach
            </ul>
            @include('theme::shop.partials.totals', ['totals' => $order->totals(), 'storefront' => $storefront, 'currency' => $order->currency])
        </aside>
    </div>
@endsection
