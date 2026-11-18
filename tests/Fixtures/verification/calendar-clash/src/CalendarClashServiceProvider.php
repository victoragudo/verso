<?php

namespace Verso\Plugins\CalendarClash;

use App\Verso\Content\ContentType;
use App\Verso\Plugins\PluginServiceProvider;

// Fixture plugin whose tables, routes, config and classes clash with the site.
class CalendarClashServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/verso.php'), 'verso');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom($this->path('routes/web.php'));
        $this->registerContentType(new ContentType(name: 'post', label: 'Event', pluralLabel: 'Events'));
    }
}
