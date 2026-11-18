<?php

namespace Verso\Plugins\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Checks the "Add to cart" form.
class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.max(1, (int) config('shop.max_quantity'))],
        ];
    }

    public function quantity(): int
    {
        return $this->filled('quantity') ? $this->integer('quantity') : 1;
    }
}
