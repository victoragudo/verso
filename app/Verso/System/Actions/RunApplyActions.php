<?php

namespace App\Verso\System\Actions;

use App\Verso\Plugins\BlockOutputCache;
use App\Verso\System\Config\ApplyAction;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Artisan;

// Clears caches or restarts queue workers after settings change, so the new values are used.
class RunApplyActions
{
    public function __construct(protected Container $container) {}

    /**
     * @param  list<ApplyAction>  $actions
     * @return list<string> labels of what was done
     */
    public function handle(array $actions): array
    {
        $done = [];

        foreach (array_unique($actions, SORT_REGULAR) as $action) {
            match ($action) {
                // Workers read the new config when they start again. The command only sets a cache flag.
                ApplyAction::RestartQueue => Artisan::call('queue:restart'),
                ApplyAction::FlushPageCache => $this->container->make(PageCache::class)->flush(),
                ApplyAction::FlushBlockCache => $this->container->make(BlockOutputCache::class)->flush(),
            };
            $done[] = $action->label();
        }

        return $done;
    }
}
