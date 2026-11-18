<?php

namespace Verso\Plugins\Shop\Mail;

use App\Verso\Core\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Payments\PaymentGateways;
use Verso\Plugins\Shop\Support\Money;

// Sent when an order is placed: to the customer, and a copy to the shop owner.
class OrderReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public bool $forShop = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->forShop
                ? __('shop::New order :number from :name', ['number' => $this->order->number(), 'name' => $this->order->customer_name])
                : __('shop::We received your order :number', ['number' => $this->order->number()]),
            replyTo: $this->forShop ? [$this->order->customer_email] : [],
        );
    }

    public function content(): Content
    {
        $gateways = app(PaymentGateways::class);

        return new Content(
            markdown: 'shop::mail.order-received',
            with: [
                'order' => $this->order->loadMissing('items'),
                'money' => app(Money::class),
                'siteName' => (string) app(Settings::class)->get('site.name'),
                'forShop' => $this->forShop,
                'paymentLabel' => $gateways->label($this->order->payment_gateway),
                'instructions' => $gateways->find($this->order->payment_gateway)?->instructions($this->order) ?? '',
            ],
        );
    }
}
