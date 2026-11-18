<?php

namespace App\Verso\Site\Actions;

use App\Verso\Site\Models\Menu;
use Illuminate\Support\Facades\DB;

// Renames a menu and changes its theme location.
class UpdateMenu
{
    public function handle(Menu $menu, string $name, ?string $location): Menu
    {
        return DB::transaction(function () use ($menu, $name, $location): Menu {
            $menu->name = trim($name);

            return $menu->assignLocation($location);
        });
    }
}
