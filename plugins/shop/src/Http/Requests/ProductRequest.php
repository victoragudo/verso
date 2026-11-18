<?php

namespace Verso\Plugins\Shop\Http\Requests;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Support\Money;
use Verso\Plugins\Shop\Support\ShopSettings;

// Checks the price, SKU, stock, gallery and variants of a product.
class ProductRequest extends FormRequest
{
    // A plain amount with a dot, like 24 or 24.90.
    public const AMOUNT = '/^\d{1,10}(\.\d{1,4})?$/';

    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Capability::ManageShop->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $productId = $this->product()?->id;

        return [
            'price' => ['required', 'string', 'regex:'.self::AMOUNT],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('shop_products', 'sku')->ignore($productId)],
            'track_stock' => ['required', 'boolean'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'gallery' => ['nullable', 'array', 'max:30'],
            'gallery.*' => ['integer', Rule::exists('media', 'id')],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.option_name' => ['required', 'string', 'max:60'],
            'variants.*.option_value' => ['required', 'string', 'max:60'],
            'variants.*.sku' => [
                'nullable', 'string', 'max:64', 'distinct',
                Rule::unique('shop_product_variants', 'sku')->where(fn ($query) => $query->where('product_id', '!=', $productId ?? 0)),
            ],
            'variants.*.price' => ['nullable', 'string', 'regex:'.self::AMOUNT],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'price.regex' => __('shop::Use a number with a dot, like 24.90.'),
            'variants.*.price.regex' => __('shop::Use a number with a dot, like 24.90.'),
            'variants.*.option_name.required' => __('shop::Each option needs a name, like Size.'),
            'variants.*.option_value.required' => __('shop::Each option needs a value, like Medium.'),
        ];
    }

    /**
     * Two variants cannot share the same name and value.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $seen = [];

            foreach ((array) $this->input('variants', []) as $index => $variant) {
                $key = mb_strtolower(trim((string) ($variant['option_name'] ?? ''))).'|'.mb_strtolower(trim((string) ($variant['option_value'] ?? '')));

                if (isset($seen[$key])) {
                    $validator->errors()->add("variants.{$index}.option_value", __('shop::This option is listed twice.'));
                }

                $seen[$key] = true;
            }
        }];
    }

    /**
     * Clean data for SaveProduct, with amounts in minor units.
     *
     * @return array{price: int, sku: string|null, track_stock: bool, stock: int, gallery: list<int>, variants: list<array{id: int|null, option_name: string, option_value: string, sku: string|null, price: int|null, stock: int}>}
     */
    public function productData(): array
    {
        $money = app(Money::class);
        $currency = app(ShopSettings::class)->currency();

        return [
            'price' => $money->toMinor((string) $this->validated('price'), $currency),
            'sku' => $this->filled('sku') ? trim((string) $this->validated('sku')) : null,
            'track_stock' => $this->boolean('track_stock'),
            'stock' => $this->integer('stock'),
            'gallery' => array_values(array_unique(array_map(intval(...), (array) $this->validated('gallery', [])))),
            'variants' => array_values(array_map(fn (array $variant): array => [
                'id' => is_numeric($variant['id'] ?? null) ? (int) $variant['id'] : null,
                'option_name' => trim((string) $variant['option_name']),
                'option_value' => trim((string) $variant['option_value']),
                'sku' => filled($variant['sku'] ?? null) ? trim((string) $variant['sku']) : null,
                'price' => filled($variant['price'] ?? null) ? $money->toMinor((string) $variant['price'], $currency) : null,
                'stock' => (int) $variant['stock'],
            ], (array) $this->validated('variants', []))),
        ];
    }

    public function product(): ?Product
    {
        $entry = $this->route('entry');

        return $entry instanceof Entry ? Product::query()->where('entry_id', $entry->id)->first() : null;
    }
}
