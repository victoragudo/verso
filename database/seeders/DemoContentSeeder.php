<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// Demo content for a new install: photos, posts, pages, menus and comments, written by the new admin.
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([MediaSeeder::class, ContentSeeder::class, MenuSeeder::class, CommentSeeder::class, PluginDemoSeeder::class]);
    }
}
