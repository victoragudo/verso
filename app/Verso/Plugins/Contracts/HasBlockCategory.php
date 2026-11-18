<?php

namespace App\Verso\Plugins\Contracts;

// A plugin block that names its group in the editor "Add" panel, like "text" or a category the plugin adds.
interface HasBlockCategory
{
    public function category(): string;
}
