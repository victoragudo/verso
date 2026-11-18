<?php

namespace App\Verso\Easy\Starters\Core;

use App\Verso\Easy\Starters\StarterSiteRegistry;

// Registers the seven starter sites of the guided start through the public registry API.
class CoreStarterSites
{
    /** @var list<class-string<CoreStarter>> */
    public const STARTERS = [
        BlogStarter::class,
        BusinessStarter::class,
        PortfolioStarter::class,
        RestaurantStarter::class,
        ShopStarter::class,
        EventStarter::class,
        PersonalStarter::class,
    ];

    public function register(StarterSiteRegistry $registry): void
    {
        foreach (self::STARTERS as $class) {
            $registry->register((new $class)->site());
        }
    }
}
