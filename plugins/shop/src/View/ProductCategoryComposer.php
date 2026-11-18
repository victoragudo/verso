<?php

namespace Verso\Plugins\Shop\View;

use App\Verso\Content\Models\Term;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Verso\Plugins\Shop\Support\Storefront;

// Gives the product category page its products (also from child categories), the sort order and the sub categories.
class ProductCategoryComposer
{
    public function __construct(protected Storefront $storefront, protected Request $request) {}

    public function compose(View $view): void
    {
        $term = $view->getData()['term'] ?? null;

        if (! $term instanceof Term) {
            return;
        }

        $sort = (string) $this->request->query('sort', 'newest');
        $sort = in_array($sort, Storefront::SORTS, true) ? $sort : 'newest';
        $products = $this->storefront->paginate($sort, (int) config('shop.per_page'), $term);

        abort_if($products->currentPage() > 1 && $products->isEmpty(), 404);

        $view->with([
            'products' => $products,
            'storefront' => $this->storefront,
            'sort' => $sort,
            'sorts' => $this->storefront->sortLabels(),
            'subcategories' => $term->children()->orderBy('name')->orderBy('id')->get(),
        ]);
    }
}
