<?php

namespace Verso\Plugins\Shop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Verso\Plugins\Shop\Actions\CancelStalePendingOrders;

// Scheduled job that cancels unpaid orders and puts their stock back.
class CancelPendingOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(CancelStalePendingOrders $cancel): void
    {
        $cancel->handle();
    }
}
