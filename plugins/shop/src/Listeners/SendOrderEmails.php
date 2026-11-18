<?php

namespace Verso\Plugins\Shop\Listeners;

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Verso\Plugins\Shop\Events\OrderPlaced;
use Verso\Plugins\Shop\Events\OrderStatusChanged;
use Verso\Plugins\Shop\Mail\OrderReceivedMail;
use Verso\Plugins\Shop\Mail\OrderStatusMail;
use Verso\Plugins\Shop\Support\ShopSettings;

// Emails the customer (and the shop owner) about new orders and status changes.
class SendOrderEmails
{
    public function __construct(
        protected ShopSettings $settings,
        protected Settings $siteSettings,
    ) {}

    public function placed(OrderPlaced $event): void
    {
        Mail::to($event->order->customer_email, $event->order->customer_name)
            ->locale($this->siteLocale())
            ->send(new OrderReceivedMail($event->order));

        $shopEmail = $this->settings->notifyEmail();

        if ($shopEmail !== null) {
            Mail::to($shopEmail)
                ->locale(User::query()->where('email', $shopEmail)->first()?->preferredLocale() ?? $this->siteLocale())
                ->send(new OrderReceivedMail($event->order, forShop: true));
        }
    }

    public function statusChanged(OrderStatusChanged $event): void
    {
        Mail::to($event->order->customer_email, $event->order->customer_name)
            ->locale($this->siteLocale())
            ->send(new OrderStatusMail($event->order, $event->from));
    }

    protected function siteLocale(): string
    {
        $locale = $this->siteSettings->get('site.locale');

        return is_string($locale) && $locale !== '' ? $locale : (string) config('app.locale');
    }
}
