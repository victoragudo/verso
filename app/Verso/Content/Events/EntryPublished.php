<?php

namespace App\Verso\Content\Events;

use App\Verso\Content\Models\Entry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Fired when an entry goes live.
class EntryPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public Entry $entry) {}
}
