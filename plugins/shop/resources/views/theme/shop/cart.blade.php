{{-- The cart: change quantities, remove products and go to the checkout. --}}
@extends('theme::layouts.app')

@section('title', __('shop::Cart'))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <header class="page-header shop-header">
        <h1 class="page-title">{{ __('shop::Your cart') }}</h1>
        @include('theme::shop.partials.nav')
    </header>

    @include('theme::shop.partials.notices')

    @if ($lines->isEmpty())
        <p class="empty">{!! __('shop::Your cart is empty. <a href=":url">Visit the shop</a> to find something you like.', ['url' => e(route('theme.shop.index'))]) !!}</p>
    @else
        <div class="shop-layout">
            <form id="shop-cart" method="post" action="{{ route('theme.shop.cart.update') }}">
                @csrf
                @method('PATCH')
                <table class="shop-table">
                    <caption class="visually-hidden">{{ __('shop::Products in your cart') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('shop::Product') }}</th>
                            <th scope="col">{{ __('shop::Price') }}</th>
                            <th scope="col">{{ __('shop::Quantity') }}</th>
                            <th scope="col" class="shop-table__amount">{{ __('shop::Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr>
                                <th scope="row" data-label="{{ __('shop::Product') }}">
                                    <a href="{{ $line->url() }}">{{ $line->name() }}</a>
                                    @if ($line->variantLabel())
                                        <span class="shop-muted">{{ $line->variantLabel() }}</span>
                                    @endif
                                    <button class="shop-link-button" type="submit" form="shop-remove-{{ $line->key }}">{{ __('shop::Remove') }}</button>
                                </th>
                                <td data-label="{{ __('shop::Price') }}">{{ $storefront->format($line->unitPrice()) }}</td>
                                <td data-label="{{ __('shop::Quantity') }}">
                                    <label class="visually-hidden" for="quantity-{{ $line->key }}">{{ __('shop::Quantity of :name', ['name' => $line->name()]) }}</label>
                                    <input class="shop-quantity" id="quantity-{{ $line->key }}" type="number" name="quantities[{{ $line->key }}]" value="{{ $line->quantity }}" min="0" max="{{ $line->maxQuantity() }}" inputmode="numeric">
                                </td>
                                <td data-label="{{ __('shop::Total') }}" class="shop-table__amount">{{ $storefront->format($line->total()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button class="shop-button shop-button--quiet" type="submit">{{ __('shop::Update cart') }}</button>
            </form>

            @foreach ($lines as $line)
                <form id="shop-remove-{{ $line->key }}" method="post" action="{{ route('theme.shop.cart.remove', ['line' => $line->key]) }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach

            <aside class="shop-summary" aria-label="{{ __('shop::Order summary') }}">
                @include('theme::shop.partials.totals', ['totals' => $totals, 'storefront' => $storefront])
                <a class="shop-button" href="{{ route('theme.shop.checkout') }}">{{ __('shop::Go to checkout') }}</a>
            </aside>
        </div>
    @endif
@endsection
