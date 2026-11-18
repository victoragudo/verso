<?php

namespace Verso\Plugins\Shop\Console;

use Illuminate\Console\Command;
use Verso\Plugins\Shop\Actions\CancelStalePendingOrders;

// php artisan shop:cancel-pending cancels unpaid orders that waited too long.
class CancelPendingOrdersCommand extends Command
{
    protected $signature = 'shop:cancel-pending';

    protected $description = 'Cancel pending shop orders that were not paid in time and put their stock back';

    public function handle(CancelStalePendingOrders $cancel): int
    {
        $count = $cancel->handle();
        $this->info("{$count} pending orders cancelled.");

        return self::SUCCESS;
    }
}
