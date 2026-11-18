<?php

namespace App\Verso\Plugins\Contracts;

// A plugin block whose HTML depends only on its own attrs and children, never on the visitor, the time or other
// content. Entries with only core and cacheable blocks keep their HTML in the block cache.
interface CacheableBlock {}
