<?php

namespace App\Verso\Editor\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Editor\Assets\BlockAssetRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Serves the CSS, JavaScript and JSON files of blocks. The URL holds a version, so browsers keep them for a year.
class BlockAssetController extends Controller
{
    protected const array TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
    ];

    public function __invoke(Request $request, BlockAssetRegistry $assets, string $asset): Response
    {
        [$key, $kind] = explode('.', $asset, 2);
        $file = $assets->file($key, $kind);

        abort_if($file === null, 404);

        $etag = '"'.substr(hash_file('xxh128', $file) ?: '', 0, 16).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag]);
        }

        return response((string) file_get_contents($file), 200, [
            'Content-Type' => self::TYPES[$kind],
            'Cache-Control' => 'public, max-age='.(int) config('verso.blocks.asset_cache_seconds').', immutable',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
