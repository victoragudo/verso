<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Actions\ChangeOrderStatus;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Mail\OrderStatusMail;
use Verso\Plugins\Shop\Support\ShopSettings;

beforeEach(function () {
    ShopFixtures::boot();
    Mail::fake();
    app(ShopSettings::class)->save(['tax_rate' => 2100, 'shipping_rate' => 495]);
    $this->admin = User::factory()->admin()->create(['name' => 'Marta Ibáñez']);
    $this->mug = ShopFixtures::product('Stoneware Mug', 2200, ['sku' => 'MUG-STONE', 'track_stock' => true, 'stock' => 10]);
});

it('lists orders newest first with counts per status', function () {
    $first = ShopFixtures::order([[$this->mug, 1]]);
    $second = ShopFixtures::order([[$this->mug, 3]], customer: ['customer_name' => 'Jonas Becker', 'customer_email' => 'jonas.becker@example.de']);
    app(ChangeOrderStatus::class)->handle($first, OrderStatus::Paid);

    $this->actingAs($this->admin)
        ->get(route('admin.shop.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/shop/orders/index')
            ->has('orders.data', 2)
            ->where('orders.data.0.number', $second->number())
            ->where('orders.data.0.customer', 'Jonas Becker')
            ->where('orders.data.0.items', 3)
            ->where('orders.data.0.status', 'pending')
            ->where('orders.data.0.tone', 'warning')
            ->where('orders.data.0.payment', 'Bank transfer')
            ->where('orders.data.0.total', 85.85)
            ->where('orders.data.0.currency', 'EUR')
            ->where('orders.data.1.statusLabel', 'Paid')
            ->where('filters.status', 'all')
            ->where('tabs.0.count', 2)
            ->where('tabs.1.key', 'pending')
            ->where('tabs.1.count', 1)
            ->where('tabs.2.key', 'paid')
            ->where('tabs.2.count', 1)
        );
});

it('filters orders by status', function () {
    ShopFixtures::order([[$this->mug, 1]]);
    $paid = ShopFixtures::order([[$this->mug, 1]]);
    app(ChangeOrderStatus::class)->handle($paid, OrderStatus::Paid);

    $this->actingAs($this->admin)
        ->get(route('admin.shop.orders.index', ['status' => 'paid']))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $paid->id)->where('filters.status', 'paid'));

    $this->actingAs($this->admin)
        ->get(route('admin.shop.orders.index', ['status' => 'lost']))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 2)->where('filters.status', 'all'));
});

it('searches orders by number, name and email', function () {
    $lucia = ShopFixtures::order([[$this->mug, 1]]);
    $jonas = ShopFixtures::order([[$this->mug, 1]], customer: ['customer_name' => 'Jonas Becker', 'customer_email' => 'jonas.becker@example.de']);

    $search = fn (string $term) => $this->actingAs($this->admin)->get(route('admin.shop.orders.index', ['search' => $term]));

    $search($jonas->number())->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $jonas->id));
    $search((string) $lucia->id)->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $lucia->id));
    $search('becker@example')->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $jonas->id));
    $search('Lucía')->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $lucia->id));
});

it('shows one order with lines, totals, customer and history', function () {
    $order = ShopFixtures::order([[$this->mug, 2]]);

    $this->actingAs($this->admin)
        ->get(route('admin.shop.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/shop/orders/show')
            ->where('order.number', $order->number())
            ->where('order.statusLabel', 'Pending payment')
            ->where('order.customer.email', 'lucia.fernandez@example.es')
            ->where('order.address', ['Lucía Fernández', 'Carrer de la Pau 14', '3º B', '46002 Valencia', 'Comunitat Valenciana', 'ES'])
            ->where('order.notes', 'Please leave the parcel with the porter.')
            ->where('order.items.0.name', 'Stoneware Mug')
            ->where('order.items.0.sku', 'MUG-STONE')
            ->where('order.items.0.unitPrice', 22)
            ->where('order.items.0.total', 44)
            ->where('order.totals.subtotal', 44)
            ->where('order.totals.shipping', 4.95)
            ->where('order.totals.taxRate', 0.21)
            ->where('order.totals.tax', 10.28)
            ->where('order.totals.total', 59.23)
            ->where('order.history.0.to', 'Pending payment')
            ->where('order.history.0.from', null)
            ->where('order.payment.gateway', 'Bank transfer')
            ->where('nextStatuses', [['value' => 'paid', 'label' => 'Paid'], ['value' => 'cancelled', 'label' => 'Cancelled']])
            ->where('statusUrl', route('admin.shop.orders.status', $order))
        );
});

it('changes the status of an order', function () {
    $order = ShopFixtures::order([[$this->mug, 2]]);

    $this->actingAs($this->admin)
        ->post(route('admin.shop.orders.status', $order), ['status' => 'paid', 'note' => 'Transfer arrived on Monday.'])
        ->assertRedirect(route('admin.shop.orders.show', $order));

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->statusChanges->last()->user_id)->toBe($this->admin->id)
        ->and($order->statusChanges->last()->note)->toBe('Transfer arrived on Monday.');

    Mail::assertSent(OrderStatusMail::class);
});

it('gives the stock back when an admin cancels an order', function () {
    $order = ShopFixtures::order([[$this->mug, 4]]);

    $this->actingAs($this->admin)->post(route('admin.shop.orders.status', $order), ['status' => 'cancelled']);

    expect($this->mug->fresh()->stock)->toBe(10);
});

it('refuses impossible status changes', function () {
    $order = ShopFixtures::order([[$this->mug, 1]]);

    $this->actingAs($this->admin)
        ->post(route('admin.shop.orders.status', $order), ['status' => 'shipped'])
        ->assertSessionHasErrors(['status' => 'An order that is "Pending payment" cannot become "Shipped".']);

    $this->actingAs($this->admin)
        ->post(route('admin.shop.orders.status', $order), ['status' => 'lost'])
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('is only for people who manage settings', function () {
    $order = ShopFixtures::order([[$this->mug, 1]]);
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.shop.orders.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.shop.orders.show', $order))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.shop.orders.status', $order), ['status' => 'paid'])->assertForbidden();
});

it('returns 404 for unknown orders', function () {
    $this->actingAs($this->admin)->get('/admin/shop/orders/4242')->assertNotFound();
});

it('adds the orders and shop items to the admin menu', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.shop.orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('versoNav', fn ($items) => collect($items)->pluck('key')->intersect(['shop.orders', 'shop.products', 'shop.settings'])->count() === 3));
});

it('shows paid orders on the dashboard', function () {
    $order = ShopFixtures::order([[$this->mug, 1]]);
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Paid);

    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats', fn ($stats) => collect($stats)->firstWhere('key', 'shop.orders_to_ship')['value'] === 1));
});
