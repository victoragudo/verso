<?php

namespace App\Verso\Api\Http\Resources;

use App\Verso\Content\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public JSON for one taxonomy term.
 *
 * @mixin Term
 */
class TermResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Term $term */
        $term = $this->resource;

        return [
            'id' => $term->id,
            'taxonomy' => $term->taxonomy,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'parent_id' => $term->parent_id,
            'path' => $term->path(),
            'url' => $term->url(),
            'entries_count' => (int) $term->getAttribute('entries_count'),
        ];
    }
}
