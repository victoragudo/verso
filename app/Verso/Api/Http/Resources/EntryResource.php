<?php

namespace App\Verso\Api\Http\Resources;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Fields\Theme\EntryFieldValues;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Plugins\Filters\ApiEntryFilter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public JSON for one entry. The full content is added only on the single entry endpoint.
 *
 * @mixin Entry
 */
class EntryResource extends JsonResource
{
    protected bool $withContent = false;

    // Add the blocks and the rendered HTML.
    public function withContent(bool $withContent = true): static
    {
        $this->withContent = $withContent;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Entry $entry */
        $entry = $this->resource;

        $data = [
            'id' => $entry->id,
            'type' => $entry->type,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'path' => $entry->path(),
            'url' => $entry->url(),
            'excerpt' => $entry->excerpt,
            'parent_id' => $entry->parent_id,
            'menu_order' => $entry->menu_order,
            'published_at' => $entry->published_at?->toIso8601String(),
            'updated_at' => $entry->updated_at?->toIso8601String(),
            'author' => $entry->author === null ? null : [
                'id' => $entry->author->id,
                'name' => $entry->author->name,
            ],
            'terms' => $entry->terms
                ->groupBy('taxonomy')
                ->map(fn ($terms) => $terms->map(fn (Term $term): array => [
                    'id' => $term->id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'url' => $term->url(),
                ])->values()->all())
                ->all(),
            'fields' => (object) app(EntryFieldValues::class)->json($entry),
        ];

        if ($this->withContent) {
            $data['blocks'] = $entry->blocks ?? [];
            $data['html'] = app(ContentRenderer::class)->render($entry);
        }

        return ApiEntryFilter::apply($data, $entry);
    }
}
