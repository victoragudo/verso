<?php

namespace Harbor\PodcastPlayer;

use App\Verso\Plugins\Filters\ApiEntryFilter;
use App\Verso\Plugins\PluginServiceProvider;

// Test plugin installed as a Composer package.
class PodcastPlayerServiceProvider extends PluginServiceProvider
{
    public function boot(): void
    {
        $this->addFilter(ApiEntryFilter::class, fn (array $data): array => [...$data, 'podcast' => 'harbor']);
    }
}
