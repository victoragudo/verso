<?php

namespace Verso\Plugins\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Checks the quantities sent from the cart page.
class UpdateCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'integer', 'min:0', 'max:'.max(1, (int) config('shop.max_quantity'))],
        ];
    }

    /** @return array<string, int> */
    public function quantities(): array
    {
        $quantities = [];

        foreach ((array) $this->validated('quantities') as $key => $quantity) {
            $quantities[(string) $key] = (int) $quantity;
        }

        return $quantities;
    }
}
