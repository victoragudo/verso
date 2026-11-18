<?php

namespace App\Verso\Site\Actions;

use App\Verso\Site\Models\Menu;
use Illuminate\Support\Facades\DB;

// Deletes a menu and all its items.
class DeleteMenu
{
    public function handle(Menu $menu): void
    {
        DB::transaction(function () use ($menu): void {
            $menu->items()->getQuery()->whereNotNull('parent_id')->delete();
            $menu->items()->getQuery()->delete();
            $menu->delete();
        });
    }
}
