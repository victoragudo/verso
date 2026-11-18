<?php

namespace App\Verso\Theme\Http\Controllers;

use App\Verso\Theme\ThemeManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// Serves a font or image from the assets folder of an installed theme (or its parents).
class ThemeAssetController
{
    public function __invoke(ThemeManager $themes, string $theme, string $path): BinaryFileResponse
    {
        $file = $themes->assetFile($theme, $path);

        abort_if($file === null, 404);

        return response()->file($file, [
            'Cache-Control' => 'public, max-age='.max(0, (int) config('verso.themes.assets.cache_seconds')).', immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
