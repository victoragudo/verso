<?php

namespace App\Verso\Theme\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Tells if a response is a plain HTML page that middleware may change.
final class HtmlResponse
{
    // Files, streams, feeds, JSON and text answers are never HTML pages.
    public static function is(Response $response): bool
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse || $response->getContent() === false) {
            return false;
        }

        $type = (string) $response->headers->get('Content-Type', 'text/html');

        return str_contains(strtolower($type), 'text/html');
    }
}
