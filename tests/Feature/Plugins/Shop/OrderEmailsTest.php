<?php

use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Mail\OrderReceivedMail;
use Verso\Plugins\Shop\Mail\OrderStatusMail;
use Verso\Plugins\Shop\Support\ShopSettings;

beforeEach(function () {
    ShopFixtures::boot();
    Mail::fake();
    app(Settings::class)->set('site.name', 'Harbour Pottery');
    app(ShopSettings::class)->save([
        'tax_rate' => 2100,
        'shipping_rate' => 495,
        'bank_transfer_instructions' => 'IBAN ES91 2100 0418 4502 0005 1332',
    ]);
    $tote = ShopFixtures::product('Canvas Tote', 1900, variants: [['Size', 'Large', 2400, 4]]);
    $this->order = ShopFixtures::order([
        [ShopFixtures::product('Stoneware Mug', 2200), 2],
        [$tote, 1, $tote->variants->first()],
    ]);
});

it('thanks the customer and lists the order', function () {
    $mail = new OrderReceivedMail($this->order);

    $mail->assertHasSubject('We received your order '.$this->order->number());
    $mail->assertSeeInHtml('Hi Lucía Fernández');
    $mail->assertSeeInOrderInHtml(['Stoneware Mug', '€44.00', 'Canvas Tote', 'Size: Large', '€24.00', 'Subtotal', '€68.00', 'Shipping', '€4.95', 'Tax', 'Total']);
    $mail->assertSeeInHtml('IBAN ES91 2100 0418 4502 0005 1332');
    $mail->assertSeeInHtml($this->order->url());
    $mail->assertSeeInHtml('Harbour Pottery');
});

it('sends the shop owner a short notice with a reply address', function () {
    $mail = new OrderReceivedMail($this->order, forShop: true);

    $mail->assertHasSubject('New order '.$this->order->number().' from Lucía Fernández');
    $mail->assertHasReplyTo('lucia.fernandez@example.es');
    $mail->assertSeeInHtml('paid with Bank transfer');
    $mail->assertDontSeeInHtml('How to pay');
});

it('tells the customer about a new status', function () {
    $this->order->forceFill(['status' => OrderStatus::Shipped])->save();

    $mail = new OrderStatusMail($this->order, OrderStatus::Paid);

    $mail->assertHasSubject('Order '.$this->order->number().': Shipped');
    $mail->assertSeeInHtml('your order is on its way');
    $mail->assertSeeInHtml('See your order');
});

it('has a message for every status', function () {
    foreach (OrderStatus::cases() as $status) {
        $this->order->status = $status;

        expect((new OrderStatusMail($this->order, OrderStatus::Pending))->message())->not->toBe('');
    }
});

it('leaves out bank details once the order is paid', function () {
    $this->order->forceFill(['status' => OrderStatus::Paid])->save();

    (new OrderReceivedMail($this->order))->assertDontSeeInHtml('IBAN');
});
