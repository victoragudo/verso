<?php

namespace App\Verso\Content\Events;

use App\Verso\Content\Models\Entry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Fired when an entry goes to the trash or is deleted for good.
class EntryDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Entry $entry, public bool $permanently = false) {}
}
