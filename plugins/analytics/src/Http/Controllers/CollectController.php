<?php

namespace Verso\Plugins\Analytics\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Verso\Plugins\Analytics\Actions\RecordPageView;
use Verso\Plugins\Analytics\Support\PageView;

// Receives the beacon. It always answers 204, so it never tells why a view was skipped.
class CollectController
{
    public function __invoke(Request $request, RecordPageView $record): Response
    {
        if ((bool) config('analytics.enabled')) {
            $record->handle(PageView::fromRequest($request));
        }

        return response()->noContent();
    }
}
