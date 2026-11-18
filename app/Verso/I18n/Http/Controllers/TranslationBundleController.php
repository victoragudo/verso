<?php

namespace App\Verso\I18n\Http\Controllers;

use App\Verso\I18n\Locales;
use App\Verso\I18n\Translation\TranslationBundle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Serves the admin strings of one language as JSON. A current version URL is cached for a long time.
class TranslationBundleController
{
    public function __invoke(Request $request, string $locale, string $version, Locales $locales, TranslationBundle $bundle): JsonResponse
    {
        abort_unless($locales->has($locale), 404);

        $current = $bundle->version($locale);
        $response = new JsonResponse($bundle->messages($locale), 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->setEtag($current);

        if ($version === $current) {
            $response->setPublic()->setMaxAge((int) config('verso.i18n.bundle_max_age'))->setImmutable();
        } else {
            // An old URL still works, but browsers must not keep the answer.
            $response->headers->set('Cache-Control', 'no-cache, private');
        }

        $response->isNotModified($request);

        return $response;
    }
}
