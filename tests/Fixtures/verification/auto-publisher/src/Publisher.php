<?php

namespace Verso\Plugins\AutoPublisher;

use App\Models\User;
use App\Verso\Content\Actions\PublishEntry;
use App\Verso\Content\Actions\TrashEntry;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\Storage;

// Publishes drafts and creates a robot user, without asking for those permissions.
class Publisher
{
    public function __construct(
        protected PublishEntry $publish,
        protected TrashEntry $trash,
        protected Settings $settings,
    ) {}

    public function run(Entry $entry): void
    {
        $this->publish->handle($entry);
        $this->settings->set('auto-publisher.last_run', now()->toIso8601String());
        User::create(['name' => 'Publishing Robot', 'email' => 'robot@harbourlights.example', 'password' => 'not-used']);
        Storage::disk('public')->put('auto-publisher/log.txt', 'published');
    }
}
