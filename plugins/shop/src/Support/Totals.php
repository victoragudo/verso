<?php

namespace Verso\Plugins\Shop\Support;

// The money parts of a cart or an order, all in minor units.
final readonly class Totals
{
    public function __construct(
        public int $subtotal,
        public int $shipping,
        public int $tax,
        public int $taxRate,
    ) {}

    /**
     * Work out shipping and tax for a subtotal.
     *
     * $taxRate is in basis points (2100 = 21%). $freeShippingOver is null when shipping is never free.
     */
    public static function calculate(
        int $subtotal,
        int $shippingRate,
        int $taxRate,
        bool $taxShipping = true,
        ?int $freeShippingOver = null,
    ): self {
        $shipping = match (true) {
            $subtotal <= 0 => 0,
            $freeShippingOver !== null && $subtotal >= $freeShippingOver => 0,
            default => max(0, $shippingRate),
        };

        $taxable = $subtotal + ($taxShipping ? $shipping : 0);

        return new self($subtotal, $shipping, self::percentOf($taxable, $taxRate), $taxRate);
    }

    // A share of an amount in basis points, rounded half up to a whole minor unit.
    public static function percentOf(int $amount, int $basisPoints): int
    {
        if ($amount <= 0 || $basisPoints <= 0) {
            return 0;
        }

        return intdiv($amount * $basisPoints + 5000, 10000);
    }

    public function total(): int
    {
        return $this->subtotal + $this->shipping + $this->tax;
    }

    // The tax rate as a percent, like 21.0 or 7.25.
    public function taxPercent(): float
    {
        return $this->taxRate / 100;
    }
}
