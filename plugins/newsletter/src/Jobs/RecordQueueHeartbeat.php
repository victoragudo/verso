<?php

namespace Verso\Plugins\Newsletter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Verso\Plugins\Newsletter\Support\QueueHeartbeat;

// Scheduled on the newsletter queue. When a worker runs it, the heartbeat proves campaigns can be sent.
class RecordQueueHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(QueueHeartbeat $heartbeat): void
    {
        $heartbeat->record();
    }
}
