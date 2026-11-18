<?php

namespace App\Verso\Easy\Pages;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Theme\Support\ThemeResponse;
use App\Verso\Theme\ThemeManager;
use Symfony\Component\HttpFoundation\Response;

// Draws an entry with the theme from unsaved blocks, in edit mode, for the frame of the visual page editor.
class PageFrame
{
    public function __construct(
        protected ThemeResponse $theme,
        protected BlockRenderer $blocks,
        protected ThemeManager $themes,
    ) {}

    /**
     * The real page with these blocks for this request only. Nothing is saved and nothing is cached.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    public function render(Entry $entry, array $blocks): Response
    {
        $copy = clone $entry;
        $copy->blocks = $blocks;
        // Admin requests only know the fallback theme views, so point "theme::" at the active theme.
        $this->themes->registerViews();
        $this->blocks->showMarkers();

        try {
            $response = $this->theme->entry($copy);
        } finally {
            $this->blocks->showMarkers(false);
        }

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }
}
