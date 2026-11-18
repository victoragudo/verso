<?php

namespace Verso\Plugins\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Core\Enums\Capability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\Shop\Actions\ChangeOrderStatus;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Exceptions\InvalidStatusChange;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Models\OrderItem;
use Verso\Plugins\Shop\Models\OrderStatusChange;
use Verso\Plugins\Shop\Payments\PaymentGateways;
use Verso\Plugins\Shop\Support\Money;

// Admin list and detail pages of orders, with status changes.
class OrderController extends Controller
{
    public function __construct(
        protected Money $money,
        protected PaymentGateways $gateways,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize(Capability::ManageShop->value);

        $status = OrderStatus::tryFrom((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $orders = Order::query()
            ->withSum('items', 'quantity')
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->when($search !== '', fn ($query) => $query->search($search))
            ->latest('id')
            ->paginate((int) config('verso.admin.per_page'))
            ->withQueryString();

        $counts = Order::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return Inertia::render('admin/plugins/shop/orders/index', [
            'orders' => $orders->through(fn (Order $order): array => [
                'id' => $order->id,
                'number' => $order->number(),
                'customer' => $order->customer_name,
                'email' => $order->customer_email,
                'status' => $order->status->value,
                'statusLabel' => $order->status->label(),
                'tone' => $order->status->tone(),
                'items' => (int) ($order->getAttribute('items_sum_quantity') ?? 0),
                'total' => $this->money->toMajor($order->total, $order->currency),
                'currency' => $order->currency,
                'payment' => $this->gateways->label($order->payment_gateway),
                'createdAt' => $order->created_at?->toIso8601String(),
                'url' => route('admin.shop.orders.show', $order),
            ]),
            'filters' => ['status' => $status->value ?? 'all', 'search' => $search],
            'tabs' => [
                ['key' => 'all', 'label' => __('All'), 'count' => (int) $counts->sum(), 'href' => route('admin.shop.orders.index', array_filter(['search' => $search]))],
                ...array_map(fn (OrderStatus $case): array => [
                    'key' => $case->value,
                    'label' => $case->label(),
                    'count' => (int) ($counts[$case->value] ?? 0),
                    'href' => route('admin.shop.orders.index', array_filter(['status' => $case->value, 'search' => $search])),
                ], OrderStatus::cases()),
            ],
            'indexUrl' => route('admin.shop.orders.index'),
        ]);
    }

    public function show(Order $order): Response
    {
        Gate::authorize(Capability::ManageShop->value);

        $order->load(['items', 'statusChanges.user']);
        $format = fn (int $amount): float => $this->money->toMajor($amount, $order->currency);

        return Inertia::render('admin/plugins/shop/orders/show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number(),
                'status' => $order->status->value,
                'statusLabel' => $order->status->label(),
                'tone' => $order->status->tone(),
                'currency' => $order->currency,
                'createdAt' => $order->created_at?->toIso8601String(),
                'paidAt' => $order->paid_at?->toIso8601String(),
                'customer' => [
                    'name' => $order->customer_name,
                    'email' => $order->customer_email,
                    'phone' => $order->customer_phone,
                ],
                'address' => $order->addressLines(),
                'notes' => $order->notes,
                'payment' => [
                    'gateway' => $this->gateways->label($order->payment_gateway),
                    'reference' => $order->payment_reference,
                ],
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'variant' => $item->variant_label,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unitPrice' => $format($item->unit_price),
                    'total' => $format($item->line_total),
                ])->values()->all(),
                'totals' => [
                    'subtotal' => $format($order->subtotal),
                    'shipping' => $format($order->shipping_total),
                    'tax' => $format($order->tax_total),
                    'taxRate' => $order->tax_rate / 10000,
                    'total' => $format($order->total),
                ],
                'history' => $order->statusChanges->map(fn (OrderStatusChange $change): array => [
                    'id' => $change->id,
                    'from' => $change->from_status?->label(),
                    'to' => $change->to_status->label(),
                    'user' => $change->user?->name,
                    'note' => $change->note,
                    'createdAt' => $change->created_at?->toIso8601String(),
                ])->values()->all(),
                'customerUrl' => $order->url(),
            ],
            'nextStatuses' => array_map(fn (OrderStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ], $order->status->next()),
            'statusUrl' => route('admin.shop.orders.status', $order),
            'showUrl' => route('admin.shop.orders.show', $order),
            'indexUrl' => route('admin.shop.orders.index'),
        ]);
    }

    public function updateStatus(Request $request, Order $order, ChangeOrderStatus $changeStatus): RedirectResponse
    {
        Gate::authorize(Capability::ManageShop->value);

        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $changeStatus->handle($order, OrderStatus::from($data['status']), $user, $data['note'] ?? null);
        } catch (InvalidStatusChange $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop::Order :number is now :status.', ['number' => $order->number(), 'status' => $order->status->label()])]);

        return to_route('admin.shop.orders.show', $order);
    }
}
