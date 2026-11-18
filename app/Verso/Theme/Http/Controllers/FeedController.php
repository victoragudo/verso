<?php

namespace App\Verso\Theme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Theme\Support\FeedBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// The RSS feed at "/feed".
class FeedController extends Controller
{
    public function __invoke(Request $request, FeedBuilder $feed): Response
    {
        $entries = $feed->entries(
            (string) config('verso.themes.home_content_type'),
            (int) config('verso.themes.feed_items'),
        );

        return new Response($feed->build($entries, $request->url()), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
