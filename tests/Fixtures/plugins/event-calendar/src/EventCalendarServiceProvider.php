<?php

namespace Verso\Plugins\EventCalendar;

use App\Verso\Content\ContentType;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Plugins\Filters\ApiEntryFilter;
use App\Verso\Plugins\PluginServiceProvider;

// Test plugin that uses every helper of the base provider.
class EventCalendarServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        $this->loadPluginMigrations();
    }

    public function boot(): void
    {
        $this->registerContentType(new ContentType(name: 'event', label: 'Event', pluralLabel: 'Events', hasArchive: true, routeBase: 'events'));
        $this->registerBlock(new VenueBlock);
        $this->addFilter(ApiEntryFilter::class, fn (array $data): array => [...$data, 'calendar' => 'event-calendar']);
        $this->addNavItem(new NavItem(key: 'events.calendar', label: 'Calendar', route: 'admin.dashboard', icon: 'calendar', order: 15));
    }
}
