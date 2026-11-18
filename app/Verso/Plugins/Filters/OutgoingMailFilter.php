<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Mail\OutgoingMail;

// Runs before every mail is sent. Change $mail->message (headers, body, recipients) or return $mail->cancel().
// Callbacks: fn (OutgoingMail $mail): OutgoingMail.
final class OutgoingMailFilter implements Filter
{
    public static function apply(OutgoingMail $mail): OutgoingMail
    {
        /** @var OutgoingMail */
        return app(FilterRegistry::class)->apply(self::class, $mail);
    }
}
