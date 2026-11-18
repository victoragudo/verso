{{-- The checkout: customer details, shipping address and payment method. --}}
@extends('theme::layouts.app')

@section('title', __('shop::Checkout'))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@php
    $fields = [
        ['customer_name', __('shop::Full name'), 'text', true, 'name'],
        ['customer_email', __('shop::Email'), 'email', true, 'email'],
        ['customer_phone', __('shop::Phone (optional)'), 'tel', false, 'tel'],
        ['address_line1', __('shop::Address'), 'text', true, 'address-line1'],
        ['address_line2', __('shop::Apartment, floor (optional)'), 'text', false, 'address-line2'],
        ['postal_code', __('shop::Postal code'), 'text', true, 'postal-code'],
        ['city', __('shop::City'), 'text', true, 'address-level2'],
        ['region', __('shop::Region or state (optional)'), 'text', false, 'address-level1'],
        ['country', __('shop::Country code'), 'text', true, 'country'],
    ];
@endphp

@section('content')
    <header class="page-header shop-header">
        <h1 class="page-title">{{ __('shop::Checkout') }}</h1>
        @include('theme::shop.partials.nav')
    </header>

    @include('theme::shop.partials.notices')

    <div class="shop-layout">
        <form class="shop-form" method="post" action="{{ route('theme.shop.checkout.store') }}">
            @csrf
            <fieldset>
                <legend>{{ __('shop::Contact and shipping') }}</legend>
                @foreach ($fields as [$name, $label, $type, $required, $autocomplete])
                    <div class="shop-field @if ($name === 'country' || $name === 'postal_code') shop-field--short @endif">
                        <label for="{{ $name }}">{{ $label }}</label>
                        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" autocomplete="{{ $autocomplete }}" @if ($required) required @endif @if ($name === 'country') maxlength="2" placeholder="ES" @endif @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
                        @error($name)
                            <p class="shop-error" id="{{ $name }}-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
                <div class="shop-field">
                    <label for="notes">{{ __('shop::Notes for your order (optional)') }}</label>
                    <textarea id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                </div>
            </fieldset>

            <fieldset>
                <legend>{{ __('shop::Payment') }}</legend>
                @forelse ($gateways as $gateway)
                    <label class="shop-choice">
                        <input type="radio" name="payment_gateway" value="{{ $gateway->key() }}" @checked(old('payment_gateway', $loop->first ? $gateway->key() : null) === $gateway->key()) required>
                        <span>
                            <strong>{{ $gateway->label() }}</strong>
                            <span class="shop-muted">{{ $gateway->description() }}</span>
                        </span>
                    </label>
                @empty
                    <p class="shop-notice shop-notice--error">{{ __('shop::The shop cannot take payments right now. Please come back later.') }}</p>
                @endforelse
                @error('payment_gateway')
                    <p class="shop-error">{{ $message }}</p>
                @enderror
            </fieldset>

            @if ($gateways !== [])
                <button class="shop-button" type="submit">{{ __('shop::Place order · :total', ['total' => $storefront->format($totals->total())]) }}</button>
            @endif
        </form>

        <aside class="shop-summary" aria-label="{{ __('shop::Order summary') }}">
            <h2>{{ __('shop::Your order') }}</h2>
            <ul class="shop-summary__lines">
                @foreach ($lines as $line)
                    <li>
                        <span>{{ $line->quantity }} × {{ $line->name() }}@if ($line->variantLabel()) <span class="shop-muted">({{ $line->variantLabel() }})</span>@endif</span>
                        <span>{{ $storefront->format($line->total()) }}</span>
                    </li>
                @endforeach
            </ul>
            @include('theme::shop.partials.totals', ['totals' => $totals, 'storefront' => $storefront])
            <a href="{{ route('theme.shop.cart') }}">{{ __('shop::Change your cart') }}</a>
        </aside>
    </div>
@endsection
