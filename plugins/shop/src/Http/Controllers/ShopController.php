<?php

namespace Verso\Plugins\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Verso\Plugins\Shop\Support\Storefront;

// The shop page: a grid of every product that is for sale.
class ShopController extends Controller
{
    public function __invoke(Request $request, Storefront $storefront, ThemeResponse $theme): Response
    {
        $sort = (string) $request->query('sort', 'newest');
        $sort = in_array($sort, Storefront::SORTS, true) ? $sort : 'newest';

        return $theme->view('shop.index', [
            'products' => $storefront->paginate($sort, (int) config('shop.per_page')),
            'storefront' => $storefront,
            'sort' => $sort,
            'sorts' => $storefront->sortLabels(),
            'categories' => $storefront->categories()->whereNull('parent_id')->values(),
        ]);
    }
}
