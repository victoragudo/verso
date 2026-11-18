<?php

namespace Verso\Plugins\Memberships\Console;

use Illuminate\Console\Command;
use Verso\Plugins\Memberships\Actions\ExpireMemberships;

// php artisan memberships:expire closes memberships past their end date.
class ExpireMembershipsCommand extends Command
{
    protected $signature = 'memberships:expire';

    protected $description = 'Mark memberships past their end date as expired';

    public function handle(ExpireMemberships $expire): int
    {
        $count = $expire->handle();
        $this->info("{$count} memberships expired.");

        return self::SUCCESS;
    }
}
