<?php

namespace App\Verso\Easy\Styles\Core;

use App\Verso\Easy\Styles\FontPair;
use App\Verso\Easy\Styles\FontPairRegistry;

// Registers the core font pairs, made from the safe font list, through the public registry API.
class CoreFontPairs
{
    public function register(FontPairRegistry $registry): void
    {
        $registry->register(new FontPair(key: 'classic', label: 'Classic', heading: 'georgia', body: 'charter', sample: 'Fresh bread every morning, baked by the harbor.', order: 10));
        $registry->register(new FontPair(key: 'modern', label: 'Clean and modern', heading: 'system', body: 'system', sample: 'Open seven days a week, from eight to six.', order: 20));
        $registry->register(new FontPair(key: 'friendly', label: 'Friendly', heading: 'humanist', body: 'humanist', sample: 'Come in, sit down and stay for a coffee.', order: 30));
        $registry->register(new FontPair(key: 'editorial', label: 'Editorial', heading: 'newsreader', body: 'charter', sample: 'Stories from the old town, told slowly.', order: 40));
        $registry->register(new FontPair(key: 'studio', label: 'Studio', heading: 'instrument-sans', body: 'newsreader', sample: 'Photographs of quiet places and the people in them.', order: 50));
        $registry->register(new FontPair(key: 'modern-serif', label: 'Modern with a serif', heading: 'system', body: 'charter', sample: 'Plans, prices and answers in plain words.', order: 60));
        $registry->register(new FontPair(key: 'warm-contrast', label: 'Warm contrast', heading: 'humanist', body: 'georgia', sample: 'Handmade ceramics from a small studio in Porto.', order: 70));
    }
}
