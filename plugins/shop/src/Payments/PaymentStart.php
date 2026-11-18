<?php

namespace Verso\Plugins\Shop\Payments;

// What happens after the checkout: go to a payment page, or show the order page.
final readonly class PaymentStart
{
    protected function __construct(public ?string $redirectUrl) {}

    // Send the customer to an outside payment page.
    public static function redirect(string $url): self
    {
        return new self($url);
    }

    // Nothing more to do now: show the order page.
    public static function done(): self
    {
        return new self(null);
    }

    public function redirects(): bool
    {
        return $this->redirectUrl !== null;
    }
}
