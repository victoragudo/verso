<?php

namespace App\Verso\Content\Events;

use App\Verso\Content\Models\Entry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Fired after an entry is created or changed.
class EntrySaved
{
    use Dispatchable, SerializesModels;

    public function __construct(public Entry $entry) {}
}
