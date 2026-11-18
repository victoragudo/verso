<?php

namespace Verso\Plugins\Shop\Mail;

use App\Verso\Core\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Support\Money;

// Tells the customer that their order moved to another status.
class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public OrderStatus $previousStatus,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('shop::Order :number: :status', ['number' => $this->order->number(), 'status' => $this->order->status->label()]));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'shop::mail.order-status',
            with: [
                'order' => $this->order->loadMissing('items'),
                'money' => app(Money::class),
                'siteName' => (string) app(Settings::class)->get('site.name'),
                'message' => $this->message(),
            ],
        );
    }

    // One friendly sentence about the new status.
    public function message(): string
    {
        return match ($this->order->status) {
            OrderStatus::Paid => __('shop::We received your payment. We are getting your order ready.'),
            OrderStatus::Shipped => __('shop::Good news: your order is on its way.'),
            OrderStatus::Completed => __('shop::Your order is complete. Thank you for shopping with us.'),
            OrderStatus::Cancelled => __('shop::Your order was cancelled. If you already paid, we will be in touch about your money.'),
            OrderStatus::Refunded => __('shop::Your order was refunded. The money should reach your account in a few days.'),
            OrderStatus::Pending => __('shop::Your order is waiting for payment.'),
        };
    }
}
