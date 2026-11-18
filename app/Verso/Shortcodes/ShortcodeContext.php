<?php

namespace App\Verso\Shortcodes;

use App\Verso\Content\Models\Entry;
use App\Verso\Shortcodes\Enums\ShortcodePlace;

// Where a text is expanded and for which entry.
final readonly class ShortcodeContext
{
    public function __construct(
        public ShortcodePlace $place = ShortcodePlace::Content,
        public ?Entry $entry = null,
        // Null asks the expander to check the signed in user.
        public ?bool $warnings = null,
    ) {}

    public static function for(ShortcodePlace $place, ?Entry $entry = null): self
    {
        return new self($place, $entry);
    }
}
