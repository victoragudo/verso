<?php

use App\Models\User;
use App\Verso\Core\Admin\AdminNavRegistry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Enums\OrderStatus;

beforeEach(function () {
    ShopFixtures::boot();
    Mail::fake();
    $this->manager = User::factory()->shopManager()->create(['name' => 'Marta Soler', 'email' => 'marta.soler@ceramica-valencia.example']);
});

it('turns the shop manager role on with the plugin', function () {
    expect(app(OptionalRoles::class)->isEnabled(Role::ShopManager))->toBeTrue()
        ->and(app(OptionalRoles::class)->available())->toContain(Role::ShopManager);
});

it('gives shop.manage to admins and shop managers only', function () {
    expect(User::factory()->admin()->create()->can('shop.manage'))->toBeTrue()
        ->and($this->manager->can('shop.manage'))->toBeTrue()
        ->and(User::factory()->editor()->create()->can('shop.manage'))->toBeFalse()
        ->and(User::factory()->author()->create()->can('shop.manage'))->toBeFalse();
});

it('does not give shop managers the site settings', function () {
    expect($this->manager->can(Capability::ManageSettings->value))->toBeFalse()
        ->and($this->manager->can(Capability::ManageUsers->value))->toBeFalse()
        ->and($this->manager->can(Capability::EditAnyEntry->value))->toBeFalse();
});

it('lets shop managers work with orders, inventory and shop settings', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 4]);
    $order = ShopFixtures::order([[$mug, 1]]);

    $this->actingAs($this->manager);

    $this->get(route('admin.shop.orders.index'))->assertOk();
    $this->get(route('admin.shop.orders.show', $order))->assertOk();
    $this->post(route('admin.shop.orders.status', $order), ['status' => OrderStatus::Paid->value])->assertRedirect();
    $this->get(route('admin.shop.products.index'))->assertOk();
    $this->get(route('admin.shop.settings.edit'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('keeps editors out of orders and shop settings', function () {
    $editor = User::factory()->editor()->create();
    $order = ShopFixtures::order([[ShopFixtures::product('Linen Apron', 3400), 1]]);

    $this->actingAs($editor)->get(route('admin.shop.orders.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.shop.orders.show', $order))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.shop.settings.edit'))->assertForbidden();
});

it('shows the shop nav items to shop managers', function () {
    $keys = collect(app(AdminNavRegistry::class)->forUser($this->manager))->pluck('key')->all();

    expect($keys)->toContain('shop.orders')
        ->toContain('shop.products')
        ->toContain('shop.settings');
});

it('lets admins give the shop manager role', function () {
    $user = User::factory()->author()->create(['name' => 'Jordi Puig']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.role', $user), ['role' => 'shop_manager'])
        ->assertRedirect();

    expect($user->fresh()->role)->toBe(Role::ShopManager)
        ->and(Role::ShopManager->label())->toBe('Shop manager');
});
