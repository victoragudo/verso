<?php

namespace App\Verso\Layouts;

use App\Models\User;
use App\Verso\Editor\Support\RenderContext;
use App\Verso\Layouts\Conditions\ConditionRegistry;
use App\Verso\Layouts\Conditions\PageContext;
use App\Verso\Layouts\Models\SiteLayout;
use Illuminate\Support\Facades\Auth;

// Picks the layouts that show on the page being drawn: the most specific template, header and footer,
// and every matching popup. Higher priority wins first, then the more specific rule, then the newest layout.
class LayoutResolver
{
    public function __construct(
        protected LayoutStore $store,
        protected ConditionRegistry $conditions,
        protected RenderContext $render,
    ) {}

    public function page(): PageContext
    {
        $user = Auth::user();

        return PageContext::fromRender($this->render, $user instanceof User ? $user : null);
    }

    public function best(LayoutKind $kind, ?PageContext $page = null): ?SiteLayout
    {
        return $this->matching($kind, $page)[0] ?? null;
    }

    /**
     * Every live layout of a kind that shows on the page, best first.
     *
     * @return list<SiteLayout>
     */
    public function matching(LayoutKind $kind, ?PageContext $page = null): array
    {
        $page ??= $this->page();
        $scored = [];

        foreach ($this->store->live($kind) as $layout) {
            $score = $this->store->isPreview($layout) ? PHP_INT_MAX : $this->conditions->score($layout->conditions, $page);

            if ($score !== null) {
                $scored[] = [$layout, $score];
            }
        }

        usort($scored, fn (array $a, array $b): int => [$b[0]->priority, $b[1], $b[0]->id] <=> [$a[0]->priority, $a[1], $a[0]->id]);

        return array_map(fn (array $item): SiteLayout => $item[0], $scored);
    }
}
