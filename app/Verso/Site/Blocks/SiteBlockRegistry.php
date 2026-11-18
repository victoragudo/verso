<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Plugins\Blocks\PluginBlockRegistry;

// Blocks offered in the Site Editor (logo, menu, footer links...). Plugins can add their own, like a cart button.
// They are kept apart from the entry editor list, so posts and pages do not show header blocks.
class SiteBlockRegistry extends PluginBlockRegistry {}
