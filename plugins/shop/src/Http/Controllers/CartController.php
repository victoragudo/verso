<?php

namespace Verso\Plugins\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Verso\Plugins\Shop\Cart\Cart;
use Verso\Plugins\Shop\Exceptions\OutOfStock;
use Verso\Plugins\Shop\Http\Requests\AddToCartRequest;
use Verso\Plugins\Shop\Http\Requests\UpdateCartRequest;
use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Support\Storefront;

// The cart page and the forms that change the cart.
class CartController extends Controller
{
    public function __construct(protected Cart $cart) {}

    public function show(ThemeResponse $theme, Storefront $storefront): Response
    {
        return $theme->view('shop.cart', [
            'cart' => $this->cart,
            'lines' => $this->cart->lines(),
            'totals' => $this->cart->totals(),
            'storefront' => $storefront,
        ]);
    }

    public function store(AddToCartRequest $request): RedirectResponse
    {
        $product = Product::query()->sellable()->with(['entry', 'variants'])->find($request->integer('product_id'));

        if ($product === null) {
            return back()->withErrors(['product_id' => __('shop::This product is not for sale.')]);
        }

        $variantId = $request->filled('variant_id') ? $request->integer('variant_id') : null;
        $variant = $product->findVariant($variantId);

        if ($product->hasVariants() && $variant === null) {
            return back()->withErrors(['variant_id' => __('shop::Please choose an option.')])->withInput();
        }

        if (! $product->hasVariants() && $variantId !== null) {
            return back()->withErrors(['variant_id' => __('shop::This product has no options.')]);
        }

        try {
            $line = $this->cart->add($product, $variant, $request->quantity());
        } catch (OutOfStock $exception) {
            return back()->withErrors(['quantity' => $exception->getMessage()])->withInput();
        }

        return to_route('theme.shop.cart')->with('shop.notice', __('shop::":name" was added to your cart.', ['name' => $line->name()]));
    }

    public function update(UpdateCartRequest $request): RedirectResponse
    {
        try {
            foreach ($request->quantities() as $key => $quantity) {
                $this->cart->update($key, $quantity);
            }
        } catch (OutOfStock $exception) {
            return to_route('theme.shop.cart')->withErrors(['quantities' => $exception->getMessage()]);
        }

        return to_route('theme.shop.cart')->with('shop.notice', __('shop::Your cart was updated.'));
    }

    public function destroy(string $line): RedirectResponse
    {
        $this->cart->remove($line);

        return to_route('theme.shop.cart')->with('shop.notice', __('shop::The product was removed from your cart.'));
    }
}
