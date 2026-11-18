<?php

namespace Database\Seeders;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Site\Actions\CreateMenu;
use App\Verso\Site\Actions\SyncMenuItems;
use App\Verso\Site\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Seeds the primary and footer menus with links to pages, categories and the blog.
 *
 * @phpstan-type MenuItemData array{type: string, entry_id?: int|null, term_id?: int|null, label?: string|null, url?: string|null, children?: list<array<string, mixed>>}
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $createMenu = app(CreateMenu::class);
        $syncItems = app(SyncMenuItems::class);

        // A site that already has a primary menu keeps its menus.
        if (Menu::forLocation('primary') !== null) {
            return;
        }

        $syncItems->handle($createMenu->handle('Main navigation', 'primary'), [
            $this->link('Home', '/'),
            $this->link('Blog', '/blog', [$this->category('Travel'), $this->category('Food'), $this->category('Garden'), $this->category('Work'), $this->category('Reading')]),
            $this->page('about', children: [$this->page('our-team')]),
            $this->page('contact'),
        ]);

        $syncItems->handle($createMenu->handle('Footer links', 'footer'), [
            $this->page('about'),
            $this->page('privacy-policy', 'Privacy'),
            $this->link('RSS feed', '/feed'),
            $this->page('contact'),
        ]);
    }

    /**
     * A menu item with a custom URL.
     *
     * @param  list<MenuItemData>  $children
     * @return MenuItemData
     */
    protected function link(string $label, string $url, array $children = []): array
    {
        return ['type' => 'custom', 'label' => $label, 'url' => $url, 'children' => $children];
    }

    /**
     * A menu item that links to a page by its slug.
     *
     * @param  list<MenuItemData>  $children
     * @return MenuItemData
     */
    protected function page(string $slug, ?string $label = null, array $children = []): array
    {
        $id = Entry::query()->ofType('page')->where('slug', $slug)->value('id');

        return ['type' => 'entry', 'entry_id' => is_numeric($id) ? (int) $id : null, 'label' => $label, 'children' => $children];
    }

    /** @return MenuItemData */
    protected function category(string $name): array
    {
        $id = Term::query()->inTaxonomy('category')->where('name', $name)->value('id');

        return ['type' => 'term', 'term_id' => is_numeric($id) ? (int) $id : null];
    }
}
