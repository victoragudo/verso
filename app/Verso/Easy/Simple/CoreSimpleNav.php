<?php

namespace App\Verso\Easy\Simple;

// The core sidebar items of simple mode, with plain names where the usual name is technical.
class CoreSimpleNav
{
    public function register(SimpleModeNav $nav): void
    {
        $nav->allow('dashboard', 'entries.page', 'comments', 'easy-start', 'easy-style', 'site.menus', 'easy-details');
        $nav->rename('entries.post', label: 'Blog posts');
        $nav->rename('media', label: 'Photos and files');
        $nav->rename('site.editor', label: 'Header and footer');
        $nav->rename('site.settings', label: 'Site settings');
    }
}
