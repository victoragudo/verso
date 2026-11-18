<?php

namespace Database\Seeders;

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Illuminate\Database\Seeder;

// Seeds a demo site: the admin, plugins, photos, writers, posts, pages, terms, menus, comments, plugin demo content and the demo theme.
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Verso Admin',
            'email' => 'admin@verso.test',
        ]);

        app(Settings::class)->setMany([
            'site.name' => 'Verso Journal',
            'site.tagline' => 'Slow travel, honest food and simple living.',
        ]);

        $this->call([PluginSeeder::class, MediaSeeder::class, ContentSeeder::class, MenuSeeder::class, CommentSeeder::class, PluginDemoSeeder::class, DemoThemeSeeder::class]);
    }
}
