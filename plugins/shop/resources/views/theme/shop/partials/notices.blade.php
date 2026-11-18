{{-- Messages after a cart or checkout action. --}}
@if (session('shop.notice'))
    <p class="shop-notice" role="status">{{ session('shop.notice') }}</p>
@endif
@if (session('shop.error'))
    <p class="shop-notice shop-notice--error" role="alert">{{ session('shop.error') }}</p>
@endif
@if ($errors->any())
    <div class="shop-notice shop-notice--error" role="alert">
        @foreach ($errors->all() as $message)
            <p>{{ $message }}</p>
        @endforeach
    </div>
@endif
