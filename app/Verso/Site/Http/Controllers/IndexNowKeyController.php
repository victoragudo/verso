<?php

namespace App\Verso\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Site\Seo\SeoSettings;
use Illuminate\Http\Response;

// Serves /{key}.txt so IndexNow can check that the key belongs to this site.
class IndexNowKeyController extends Controller
{
    public function __invoke(SeoSettings $seo, string $key): Response
    {
        abort_unless($seo->indexNowKey() !== null && hash_equals((string) $seo->indexNowKey(), $key), 404);

        return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
