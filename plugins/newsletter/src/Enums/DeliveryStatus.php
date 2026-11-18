<?php

namespace Verso\Plugins\Newsletter\Enums;

// The state of one campaign email to one subscriber.
enum DeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
