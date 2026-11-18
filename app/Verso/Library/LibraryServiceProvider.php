<?php

namespace App\Verso\Library;

use App\Models\User;
use App\Verso\Blocks\BlocksServiceProvider;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Library\Blocks\SyncedBlock;
use App\Verso\Library\Models\LibraryItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

// Boots the design library: synced blocks and saved patterns, offered in the Add panel of every editor.
class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LibraryStore::class, fn (): LibraryStore => new LibraryStore((int) config('verso.library.import_max')));
        $this->app->singleton(SyncedBlock::class);
    }

    public function boot(): void
    {
        AdminRoutes::load(base_path('routes/verso/library.php'));
        BlocksServiceProvider::add($this->app->make(SyncedBlock::class));
        Event::listen(LibraryChanged::class, LibraryInvalidator::class);

        // The editors get the saved items for the "Library" tab of the Add panel, and the address to save new ones.
        EditorManifest::extend(fn (?User $user): array => $user === null ? [] : [
            'library' => [
                'items' => LibraryItem::query()->orderBy('title')->limit((int) config('verso.library.editor_items'))->get()->map(fn (LibraryItem $item): array => $item->toEditor())->all(),
                'canSave' => $user->can(Capability::ManageLibrary->value),
                'storeUrl' => route('admin.library.store'),
                'editUrl' => route('admin.library.edit', ['item' => 0]),
            ],
        ]);

        AdminNav::add(new NavItem(key: 'site.library', label: 'Library', route: 'admin.library.index', icon: 'patterns', order: 18, capability: Capability::ManageLibrary, group: NavGroup::Design, keywords: ['synced blocks', 'reusable blocks', 'patterns', 'biblioteca']));
    }
}
