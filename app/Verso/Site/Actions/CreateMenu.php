<?php

namespace App\Verso\Site\Actions;

use App\Verso\Site\Models\Menu;
use Illuminate\Support\Facades\DB;

// Creates an empty menu, optionally shown in a theme location.
class CreateMenu
{
    public function handle(string $name, ?string $location = null): Menu
    {
        return DB::transaction(function () use ($name, $location): Menu {
            $menu = Menu::query()->create(['name' => trim($name)]);

            return $location === null ? $menu : $menu->assignLocation($location);
        });
    }
}
