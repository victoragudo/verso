<?php

namespace App\Verso\Media\Exceptions;

use App\Verso\Content\Models\Entry;
use App\Verso\I18n\Translate;
use Illuminate\Support\Collection;
use RuntimeException;

// Thrown when someone tries to delete a file that entries still use.
class MediaInUseException extends RuntimeException
{
    /**
     * @param  Collection<int, Entry>  $entries
     */
    public function __construct(public readonly Collection $entries)
    {
        parent::__construct(Translate::icu(
            '{count, plural, one {This file is used in # entry.} other {This file is used in # entries.}}',
            ['count' => $entries->count()],
        ));
    }
}
