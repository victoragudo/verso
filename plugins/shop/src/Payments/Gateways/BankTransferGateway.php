<?php

namespace Verso\Plugins\Shop\Payments\Gateways;

use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Payments\PaymentGateway;
use Verso\Plugins\Shop\Payments\PaymentStart;
use Verso\Plugins\Shop\Support\ShopSettings;

// Manual payment: the customer sends money to the bank account and an admin marks the order as paid.
class BankTransferGateway implements PaymentGateway
{
    public const KEY = 'bank_transfer';

    public function __construct(protected ShopSettings $settings) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('shop::Bank transfer');
    }

    public function description(): string
    {
        return __('shop::Pay from your bank account. We ship once the money arrives.');
    }

    public function isAvailable(): bool
    {
        return $this->settings->bankTransferEnabled();
    }

    public function start(Order $order): PaymentStart
    {
        return PaymentStart::done();
    }

    public function instructions(Order $order): string
    {
        if ($order->status !== OrderStatus::Pending) {
            return '';
        }

        return trim($this->settings->bankTransferInstructions());
    }
}
