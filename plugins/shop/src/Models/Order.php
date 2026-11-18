<?php

namespace Verso\Plugins\Shop\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Support\Totals;

/**
 * An order placed at the checkout. Amounts are in minor units of the order currency.
 *
 * @property int $id
 * @property string $token
 * @property OrderStatus $status
 * @property string $currency
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property string $address_line1
 * @property string|null $address_line2
 * @property string $city
 * @property string $postal_code
 * @property string|null $region
 * @property string $country
 * @property string|null $notes
 * @property int $subtotal
 * @property int $shipping_total
 * @property int $tax_total
 * @property int $total
 * @property int $tax_rate
 * @property string $payment_gateway
 * @property string|null $payment_reference
 * @property bool $stock_reduced
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderStatusChange> $statusChanges
 */
class Order extends Model
{
    protected $table = 'shop_orders';

    protected $fillable = [
        'currency', 'customer_name', 'customer_email', 'customer_phone',
        'address_line1', 'address_line2', 'city', 'postal_code', 'region', 'country', 'notes',
        'payment_gateway',
    ];

    protected $attributes = ['status' => 'pending', 'stock_reduced' => false, 'tax_rate' => 0];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'tax_rate' => 'integer',
            'stock_reduced' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->token ??= (string) Str::uuid();
        });
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /** @return HasMany<OrderStatusChange, $this> */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(OrderStatusChange::class)->orderBy('id');
    }

    // Copy the totals of the cart onto the order.
    public function applyTotals(Totals $totals): void
    {
        $this->subtotal = $totals->subtotal;
        $this->shipping_total = $totals->shipping;
        $this->tax_total = $totals->tax;
        $this->tax_rate = $totals->taxRate;
        $this->total = $totals->total();
    }

    // The saved totals as a Totals object, for views and emails.
    public function totals(): Totals
    {
        return new Totals($this->subtotal, $this->shipping_total, $this->tax_total, $this->tax_rate);
    }

    // The number people see, like "ORD-00042".
    public function number(): string
    {
        return config('shop.order_prefix').str_pad((string) $this->id, max(1, (int) config('shop.order_digits')), '0', STR_PAD_LEFT);
    }

    // The private page where the customer sees the order.
    public function url(): string
    {
        return route('theme.shop.order', ['token' => $this->token]);
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * The shipping address as lines of text.
     *
     * @return list<string>
     */
    public function addressLines(): array
    {
        return array_values(array_filter([
            $this->customer_name,
            $this->address_line1,
            $this->address_line2,
            trim($this->postal_code.' '.$this->city),
            $this->region,
            $this->country,
        ], fn (?string $line): bool => $line !== null && trim($line) !== ''));
    }

    // Find an order by its number, like "ORD-00042" or "42".
    /** @param Builder<self> $query */
    public function scopeSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        $id = ltrim(Str::after($search, (string) config('shop.order_prefix')), '0');

        $query->where(function (Builder $query) use ($search, $id): void {
            $query->where('customer_email', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%");

            if ($id !== '' && ctype_digit($id)) {
                $query->orWhere('id', (int) $id);
            }
        });
    }
}
