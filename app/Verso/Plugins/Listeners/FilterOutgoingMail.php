<?php

namespace App\Verso\Plugins\Listeners;

use App\Verso\Plugins\Filters\OutgoingMailFilter;
use App\Verso\Plugins\Mail\OutgoingMail;
use Illuminate\Mail\Events\MessageSending;

// Runs the OutgoingMailFilter before a mail is sent. Returning false stops the mail.
class FilterOutgoingMail
{
    public function handle(MessageSending $event): ?bool
    {
        $mail = OutgoingMailFilter::apply(new OutgoingMail($event->message, $event->data));

        return $mail->cancelled ? false : null;
    }
}
