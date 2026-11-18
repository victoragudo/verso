<?php

namespace Verso\Plugins\Newsletter\Actions;

use Illuminate\Support\Facades\Mail;
use Verso\Plugins\Newsletter\Enums\SubscriberStatus;
use Verso\Plugins\Newsletter\Mail\ConfirmSubscriptionMail;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Newsletter\Models\Subscriber;

// A signup from the site. New or returning addresses get a confirm email first (double opt-in).
class Subscribe
{
    public function handle(string $email, ?string $name = null, ?MailingList $list = null): Subscriber
    {
        $subscriber = Subscriber::query()->firstOrNew(['email' => mb_strtolower(trim($email))]);

        if ($name !== null && $name !== '') {
            $subscriber->name = $name;
        }

        $needsConfirm = ! $subscriber->exists || ! $subscriber->isSubscribed();

        if ($needsConfirm) {
            $subscriber->status = SubscriberStatus::Pending;
            $subscriber->source = $subscriber->exists ? $subscriber->source : 'signup';
        }

        $subscriber->save();

        if ($list !== null) {
            $subscriber->lists()->syncWithoutDetaching([$list->id]);
        }

        if ($needsConfirm) {
            Mail::to($subscriber->email)->send(new ConfirmSubscriptionMail($subscriber));
        }

        return $subscriber;
    }
}
